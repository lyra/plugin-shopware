<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);
namespace Lyranetwork\Lyra\PaymentMethods;

use Lyranetwork\Lyra\Sdk\Form\Request as LyraRequest;
use Lyranetwork\Lyra\Sdk\Form\Response as LyraResponse;
use Lyranetwork\Lyra\Sdk\Form\Api as LyraApi;
use Lyranetwork\Lyra\Sdk\Tools;
use Lyranetwork\Lyra\Service\ConfigService;
use Lyranetwork\Lyra\Service\OrderService;
use Lyranetwork\Lyra\Installer\CustomFieldInstaller;
use Lyranetwork\Lyra\Service\LocaleCodeService;

use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Psr\Log\LoggerInterface;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;

class Standard implements AsynchronousPaymentHandlerInterface
{
    /**
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;

    /**
     * @var EntityRepository
     */
    private $transactionRepository;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var LocaleCodeService
     */
    private $localeCodeService;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var string
     */
    private $shopwareVersion;

    /**
     * @var array
     */
    private $paymentResult;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        EntityRepository $transactionRepository,
        ConfigService $configService,
        LoggerInterface $logger,
        OrderService $orderService,
        LocaleCodeService $localeCodeService,
        TranslatorInterface $translator,
        RouterInterface $router,
        string $shopwareVersion
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->transactionRepository = $transactionRepository;
        $this->configService = $configService;
        $this->logger = $logger;
        $this->orderService = $orderService;
        $this->localeCodeService = $localeCodeService;
        $this->translator = $translator;
        $this->router = $router;
        $this->shopwareVersion = $shopwareVersion;
        $this->paymentResult = array(
            'lyraIsCancelledPayment' => false,
            'lyraIsPaymentError' => false
        );
    }

    /**
     * @throws PaymentException
     */
    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): RedirectResponse {
        // Method that sends the return URL to the external gateway and gets a redirect URL back.
        $order = $transaction->getOrder();

        // Get current user info.
        $customer = $salesChannelContext->getCustomer();
        if ($customer === null) {
            throw PaymentException::asyncProcessInterrupted($transaction->getOrderTransaction()->getId(), 'Customer is not logged in.');
        }

        $salesChannelId = $order->getSalesChannelId();
        $request = new LyraRequest();

        // Get current currency.
        $currency = LyraApi::findCurrencyByAlphaCode($salesChannelContext->getCurrency()->getIsoCode());

        // Get current language.
        $lang = $this->localeCodeService->getLocaleCodeFromContext($salesChannelContext->getContext());
        $lyraLang = LyraApi::isSupportedLanguage($lang) ? $lang : $this->getConfig('language', $salesChannelId);

        // Disable 3DS?
        $threedsMpi = null;
        if ($this->getConfig('3ds_min_amount', $salesChannelId) && ($transaction->getOrderTransaction()->getAmount()->getTotalPrice() < $this->getConfig('3ds_min_amount', $salesChannelId))) {
            $threedsMpi = '2';
        }

        $version = Tools::getDefault('CMS_IDENTIFIER') . '_v' . Tools::getDefault('PLUGIN_VERSION');

        $params = [
            'amount' => $currency->convertAmountToInteger($transaction->getOrderTransaction()->getAmount()->getTotalPrice()),
            'currency' => $currency->getNum(),
            'language' => $lyraLang,
            'contrib' => $version . '/' . $this->shopwareVersion . '/' . LyraApi::shortPhpVersion(),
            'order_id' => $order->getOrderNumber(),

            'cust_id' => $customer->getCustomerNumber(),
            'cust_email' => $customer->getEmail(),

            'cust_title' => $customer->getSalutation()->getDisplayName(),
            'cust_first_name' => $customer->getFirstName(),
            'cust_last_name' => $customer->getLastName(),
            'cust_address' => $customer->getDefaultBillingAddress()->getStreet(),
            'cust_zip' => $customer->getDefaultBillingAddress()->getZipcode(),
            'cust_city' => $customer->getDefaultBillingAddress()->getCity(),
            'cust_country' => $customer->getDefaultBillingAddress()->getCountry()->getIso(),
            'cust_phone' => $customer->getDefaultBillingAddress()->getPhoneNumber(),

            'ship_to_first_name' => $customer->getDefaultShippingAddress()->getFirstName(),
            'ship_to_last_name' => $customer->getDefaultShippingAddress()->getLastName(),
            'ship_to_street' => $customer->getDefaultShippingAddress()->getStreet(),
            'ship_to_zip' => $customer->getDefaultShippingAddress()->getZipcode(),
            'ship_to_city' => $customer->getDefaultShippingAddress()->getCity(),
            'ship_to_country' => $customer->getDefaultShippingAddress()->getCountry()->getIso(),
            'ship_to_phone_num' => $customer->getDefaultShippingAddress()->getPhoneNumber(),

            'threeds_mpi' => $threedsMpi,

            'url_return' => $this->router->generate('lyra_finalize', [], $this->router::ABSOLUTE_URL)
        ];

        $request->setFromArray($params);
        $request->addExtInfo('order_transaction_id', $transaction->getOrderTransaction()->getId());
        $request->addExtInfo('sales_channel_id', $salesChannelId);

        // Process billing and shipping states.
        if (! empty($customer->getDefaultBillingAddress()->getCountryState())) {
            $request->set('cust_state', $customer->getDefaultBillingAddress()->getCountryState()->getShortCode());
        }

        if (! empty($customer->getDefaultShippingAddress()->getCountryState())) {
            $request->set('ship_to_state', $customer->getDefaultShippingAddress()->getCountryState()->getShortCode());
        }

        // Set module admin parameters.
        $keys = [
            'site_id', 'key_test', 'key_prod', 'ctx_mode', 'platform_url',
            'available_languages', 'capture_delay', 'validation_mode', 'payment_cards',
            'redirect_enabled', 'return_mode', 'redirect_success_timeout',
            'redirect_success_message', 'redirect_error_timeout', 'redirect_error_message',
            'sign_algo'
        ];

        foreach ($keys as $key) {
            $value = $this->getConfig($key, $salesChannelId);
            $request->set($key, $value);
        }

        $this->logger->info("Buyer {$customer->getEmail()} sent to payment gateway for order #{$order->getOrderNumber()}.");
        $this->logger->debug('Form data: ' . print_r($request->getRequestFieldsArray(true), true));

        $msg = $this->translator->trans('lyraRedirectWaitText');

        $form = <<<EOT
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate" />
        <meta http-equiv="Cache-Control" content="post-check=0, pre-check=0" />
        <meta http-equiv="Pragma" content="no-cache" />
        <title>Redirection</title>
    </head>
    <body>
        <div style="text-align: center;">{$msg}</div>
        <form action="{$request->get('platform_url')}" method="POST" name="lyra_form">
            {$request->getRequestHtmlFields()}
        </form>

        <script type="text/javascript">
            window.onload = function() {
                document.lyra_form.submit();
            };
        </script>
    </body>
</html>
EOT;

        $redirectResponse = new RedirectResponse($request->get('platform_url'));
        $redirectResponse->setContent($form);
        $redirectResponse->headers->remove('Location');

        return $redirectResponse;
    }

    public function finalize(
        AsyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): void {
        // Load gateway response.
        $fromServer = $request->request->get('vads_hash') !== null;
        $params = (($request->getMethod() === Request::METHOD_POST) || $fromServer) ? $request->request : $request->query;

        // Context information is passed through vads_ext_info_*.
        $orderTransactionId = (string) $params->get('vads_ext_info_order_transaction_id');
        $salesChannelId = (string) $params->get('vads_ext_info_sales_channel_id');

        // Init session.
        $session = ($request->hasSession()) ? $request->getSession() : new Session();
        if (! $session->isStarted()) {
            $session->start();
        }

        $session->set('lyraGoingIntoProductionInfo', false);
        $session->set('lyraCheckUrlWarn', false);
        $session->set('lyraTechError', false);

        $lyraResponse = new LyraResponse(
            $params->all(),
            $this->getConfig('ctx_mode', $salesChannelId),
            $this->getConfig('key_test', $salesChannelId),
            $this->getConfig('key_prod', $salesChannelId),
            $this->getConfig('sign_algo', $salesChannelId)
        );

        // Check the authenticity of the request.
        if (! $lyraResponse->isAuthentified()) {
            $ip = $request->getClientIp();

            $this->logger->error("{$ip} tries to access payment_lyra/process page without valid signature with parameters: " . print_r($params->all(), true));
            $this->logger->error('Signature algorithm selected in module settings must be the same as one selected in Lyra Expert Back Office.');

            if ($fromServer) {
                $this->logger->info('IPN URL PROCESS END.');
                echo $lyraResponse->getOutputForGateway('auth_fail');
                return;
            } else {
                $session->set('lyraTechError', true);

                $this->logger->info('RETURN URL PROCESS END.');
                throw PaymentException::asyncFinalizeInterrupted($orderTransactionId, $this->translator->trans('lyraPaymentFatal'));
            }
        }

        // Retrieve order info from DB.
        $orderId = $lyraResponse->get('order_id');;
        $order = $this->orderService->filterOrderNumber($orderId, $salesChannelContext->getContext());

        if (empty($order)) {
            $this->logger->error("Error: order #$orderId not found in database.");

            if ($fromServer) {
                $this->logger->info('IPN URL PROCESS END.');
                echo $lyraResponse->getOutputForGateway('order_not_found');
                return;
            } else {
                $session->set('lyraTechError', true);

                $this->logger->info('RETURN URL PROCESS END.');
                throw PaymentException::asyncFinalizeInterrupted($orderTransactionId, $this->translator->trans('lyraPaymentFatal'));
            }
        }

        // Get current transaction status.
        $orderTransaction = $transaction->getOrderTransaction();
        $currentPaymentStatus = $orderTransaction->getStateMachineState()->getTechnicalName();

        if ($this->isNewOrderTransaction($orderTransaction, $lyraResponse)) {
            // Order not processed yet, update transaction status.
            $context = $salesChannelContext->getContext();

            $successStatus = $this->getConfig('payment_status_on_success', $salesChannelId);
            $newPaymentStatus = Tools::getNewOrderPaymentStatus($lyraResponse, $successStatus);

            if (($newPaymentStatus !== OrderTransactionStates::STATE_CANCELLED)
                && (in_array($currentPaymentStatus, array(OrderTransactionStates::STATE_CANCELLED,OrderTransactionStates::STATE_FAILED), true))) {
                $this->transactionStateHandler->reopen($transaction->getOrderTransaction()->getId(), $context);
            }

            // Payment completed, set transaction status the status configured in plugin backend.
            switch ($newPaymentStatus) {
                case OrderTransactionStates::STATE_PAID:
                    if (method_exists($this->transactionStateHandler, 'pay')) {
                        $this->transactionStateHandler->pay($transaction->getOrderTransaction()->getId(), $context);
                    } else {
                        $this->transactionStateHandler->paid($transaction->getOrderTransaction()->getId(), $context);
                    }

                    break;

                case OrderTransactionStates::STATE_CANCELLED:
                    if ($currentPaymentStatus !== OrderTransactionStates::STATE_CANCELLED) {
                        $this->transactionStateHandler->cancel($transaction->getOrderTransaction()->getId(), $context);
                    }

                    break;

                case OrderTransactionStates::STATE_FAILED:
                    $this->transactionStateHandler->fail($transaction->getOrderTransaction()->getId(), $context);
                    break;

                case OrderTransactionStates::STATE_PARTIALLY_PAID:
                    $this->transactionStateHandler->payPartially($transaction->getOrderTransaction()->getId(), $context);
                    break;

                case OrderTransactionStates::STATE_REMINDED:
                    $this->transactionStateHandler->remind($transaction->getOrderTransaction()->getId(), $context);
                    break;

                default:
                    // Unsupported states, do nothing.
                    break;
            }

            // Update order status.
            $this->orderService->saveOrderStatus($context, $transaction->getOrderTransaction()->getOrderId(), Tools::getNewOrderTransition($lyraResponse));

            // Update transaction data.
            if (! $lyraResponse->isCancelledPayment()) {
                $this->saveTransactionData($transaction, $lyraResponse, $salesChannelContext->getContext());
            }

            if ($fromServer) {
                if ($lyraResponse->isAcceptedPayment()) {
                    $this->logger->info("Payment processed successfully by IPN URL call for order #$orderId.");
                    $msg = 'payment_ok';
                } else {
                    $this->logger->info("Payment failed or cancelled for order #$orderId. {$lyraResponse->getLogMessage()}");
                    $msg = 'payment_ko';
                }

                $this->logger->info('IPN URL PROCESS END.');
                echo $lyraResponse->getOutputForGateway($msg);

                return;
            } else {
                if ($lyraResponse->isAcceptedPayment()) {
                    // Display a warning about check URL not working.
                    if ($this->getConfig('ctx_mode', $salesChannelId) === 'TEST') {
                        $session->set('lyraCheckUrlWarn', true);
                    }

                    $this->logger->info("Warning! IPN URL call has not worked. Payment completed by return URL call for order #$orderId.");
                } else {
                    $this->logger->info("Payment failed or cancelled for order #$orderId. {$lyraResponse->getLogMessage()}");
                    if ($lyraResponse->isCancelledPayment()) {
                        $this->paymentResult['lyraIsCancelledPayment'] = true;
                    } else {
                        $this->paymentResult['lyraIsPaymentError'] = true;
                    }
                }

                $this->logger->info('RETURN URL PROCESS END.');
            }
        } else {
            // Order already processed.
            $this->logger->info("Order #$orderId is already saved.");

            // Get status configured in module backend.
            $successStatus = $this->getConfig('payment_status_on_success', $salesChannelId);

            if ($currentPaymentStatus === $successStatus) {
                if ($lyraResponse->isAcceptedPayment()) {
                    $this->logger->info("Payment successful confirmed for order #$orderId.");
                    if ($fromServer) {
                        $this->logger->info('IPN URL PROCESS END.');
                        echo $lyraResponse->getOutputForGateway('payment_ok_already_done');
                    } else {
                        $this->logger->info('RETURN URL PROCESS END.');
                    }
                } else {
                    $this->logger->info("Error! Invalid payment result received for already saved order #$orderId. Payment result: {$lyraResponse->getTransStatus()}, Order status: {$currentPaymentStatus}.");
                    if ($fromServer) {
                        $this->logger->info('IPN URL PROCESS END.');
                        echo $lyraResponse->getOutputForGateway('payment_ko_on_order_ok');
                    } else {
                        $session->set('lyraTechError', true);

                        $this->logger->info('RETURN URL PROCESS END.');
                        throw PaymentException::asyncFinalizeInterrupted($orderTransactionId, $this->translator->trans('lyraPaymentFatal'));
                    }
                }
            } else {
                $this->logger->info("Payment failed or cancelled confirmed for order #$orderId.");

                if ($fromServer) {
                    $this->logger->info('IPN URL PROCESS END.');
                    echo $lyraResponse->getOutputForGateway('payment_ko_already_done');
                } else {
                    $this->logger->info('RETURN URL PROCESS END.');

                    if ($lyraResponse->isCancelledPayment()) {
                        $this->paymentResult['lyraIsCancelledPayment'] = true;
                    } else {
                        $this->paymentResult['lyraIsPaymentError'] = true;
                    }
                }
            }
        }

        if (! $fromServer && ($this->getConfig('ctx_mode', $salesChannelId) === 'TEST')
            && Tools::$pluginFeatures['prodfaq'] && $lyraResponse->isAcceptedPayment()) {
            $session->set('lyraGoingIntoProductionInfo', true);
        }
    }

    public function finalizePayment(
        AsyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext
        ): array {
            $this->finalize($transaction, $request, $salesChannelContext);
            return $this->paymentResult;
    }

    /**
     * Check if the order needs to be processed.
     *
     * @param OrderTransactionEntity $orderTransaction
     * @param LyraResponse $lyraResponse
     * @return boolean
     */
    private function isNewOrderTransaction(OrderTransactionEntity $orderTransaction, LyraResponse $lyraResponse)
    {
        $currentPaymentStatus = $orderTransaction->getStateMachineState()->getTechnicalName();

        if ($currentPaymentStatus === OrderTransactionStates::STATE_OPEN) {
            return true;
        }

        if ($currentPaymentStatus === OrderTransactionStates::STATE_FAILED) {
            $customFields = $orderTransaction->getCustomFields() ?? [];
            if (! empty($customFields)) {
                if (array_key_exists(CustomFieldInstaller::TRANSACTION_UUID, $customFields)
                    && ($customFields[CustomFieldInstaller::TRANSACTION_UUID] !== $lyraResponse->get('trans_uuid'))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Return the payment module configuration value.
     *
     * @param string $setting
     * @return mixed|null
     */
    public function getConfig(string $setting, ?string $salesChannelId = null)
    {
        return $this->configService->get($setting, $salesChannelId);
    }

    /**
     * Save transaction data.
     *
     * @param LyraResponse $lyraResponse
     * @return string
     */
    public function saveTransactionData(AsyncPaymentTransactionStruct $transaction, LyraResponse $lyraResponse, Context $context): void
    {
        // Save payment details.
        $expiry = '';
        if ($lyraResponse->get('expiry_month') && $lyraResponse->get('expiry_year')) {
            $expiry = str_pad($lyraResponse->get('expiry_month'), 2, '0', STR_PAD_LEFT) . ' / ' . $lyraResponse->get('expiry_year');
        }

        $cardBrand = $lyraResponse->get('card_brand');
        if ($lyraResponse->get('brand_management')) {
            $brandInfo = json_decode($lyraResponse->get('brand_management'));

            if (isset($brandInfo->userChoice) && $brandInfo->userChoice) {
                $brandChoice = $this->translator->trans('lyraUserChoice');
            } else {
                $brandChoice = $this->translator->trans('lyraDefaultChoice');
            }

            $cardBrand .= ' (' . $brandChoice . ')';
        }

        $data = [
            CustomFieldInstaller::TRANSACTION_ID => $lyraResponse->get('trans_id'),
            CustomFieldInstaller::TRANSACTION_UUID => $lyraResponse->get('trans_uuid'),
            CustomFieldInstaller::TRANSACTION_TYPE => $lyraResponse->get('operation_type'),
            CustomFieldInstaller::TRANSACTION_STATUS => $lyraResponse->get('trans_status'),
            CustomFieldInstaller::TRANSACTION_MESSAGE => $lyraResponse->getMessage(),
            CustomFieldInstaller::MEANS_OF_PAYMENT => $cardBrand,
            CustomFieldInstaller::CARD_NUMBER => $lyraResponse->get('card_number'),
            CustomFieldInstaller::CARD_EXPIRATION_DATE => $expiry
        ];

        $customFields = $transaction->getOrderTransaction()->getCustomFields() ?? [];
        $customFields = array_merge($customFields, $data);

        $update = [
            'id' => $transaction->getOrderTransaction()->getId(),
            'customFields' => $customFields
        ];

        $transaction->getOrderTransaction()->setCustomFields($customFields);

        $this->transactionRepository->update([$update], $context);
    }
}