<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);
namespace LyraPayment\Payzen\PaymentMethods;

use LyraPayment\Payzen\Payzen\PayzenRequest;
use LyraPayment\Payzen\Payzen\PayzenResponse;
use LyraPayment\Payzen\Payzen\PayzenApi;
use LyraPayment\Payzen\Payzen\PayzenTools;
use LyraPayment\Payzen\Service\ConfigService;
use LyraPayment\Payzen\Service\OrderService;
use LyraPayment\Payzen\Installer\PayzenCustomFieldInstaller;
use LyraPayment\Payzen\Service\LocaleCodeService;

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
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\Session;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Context;

class PaymentPayzen implements AsynchronousPaymentHandlerInterface
{
    /**
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;

    /**
     * @var EntityRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var ConfigService
     */
    protected $configService;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var OrderService
     */
    protected $orderService;

    /**
     * @var LocaleCodeService
     */
    private $localeCodeService;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var string
     */
    private $shopwareVersion;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        EntityRepositoryInterface $transactionRepository,
        ConfigService $configService,
        LoggerInterface $logger,
        OrderService $orderService,
        LocaleCodeService $localeCodeService,
        CsrfTokenManagerInterface $csrfTokenManager,
        TranslatorInterface $translator,
        string $shopwareVersion
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->transactionRepository = $transactionRepository;
        $this->configService = $configService;
        $this->logger = $logger;
        $this->orderService = $orderService;
        $this->localeCodeService = $localeCodeService;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->translator = $translator;
        $this->shopwareVersion = $shopwareVersion;
    }

    /**
     * @throws AsyncPaymentProcessException
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
            throw new AsyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                (new CustomerNotLoggedInException())->getMessage()
            );
        }

        $salesChannelId = $order->getSalesChannelId();
        $request = new PayzenRequest();

        // Get current currency.
        $currency = PayzenApi::findCurrencyByAlphaCode($salesChannelContext->getCurrency()->getIsoCode());

        // Get current language.
        $lang = $this->localeCodeService->getLocaleCodeFromContext($salesChannelContext->getContext());
        $payzenLang = PayzenApi::isSupportedLanguage($lang) ? $lang : $this->getConfig('language', $salesChannelId);

        // Disable 3DS?
        $threedsMpi = null;
        if ($this->getConfig('3ds_min_amount', $salesChannelId) && ($transaction->getOrderTransaction()->getAmount()->getTotalPrice() < $this->getConfig('3ds_min_amount', $salesChannelId))) {
            $threedsMpi = '2';
        }

        $version = PayzenTools::getDefault('CMS_IDENTIFIER') . '_v' .  PayzenTools::getDefault('PLUGIN_VERSION');

        $contextInfo = 'order_transaction_id=' . $transaction->getOrderTransaction()->getId();
        $contextInfo .= '&sales_channel_id=' . $salesChannelId;

        $params = [
            'amount' => $currency->convertAmountToInteger($transaction->getOrderTransaction()->getAmount()->getTotalPrice()),
            'currency' => $currency->getNum(),
            'language' => $payzenLang,
            'contrib' => $version . '/' . $this->shopwareVersion . '/' . PHP_VERSION,
            'order_id' => $order->getOrderNumber(),
            'order_info' => $contextInfo,

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

            'url_return'  => $transaction->getReturnUrl()
        ];

        $request->setFromArray($params);

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

        $csrfToken = $this->csrfTokenManager->getToken('payment.finalize.transaction')->getValue();
        $request->set("return_post_params", "_csrf_token=" . $csrfToken);

        $msg = $this->translator->trans('payzenRedirectWaitText');

        echo <<<EOT
        <form action="{$request->get('platform_url')}" method="POST" name="payzen_form">
            {$request->getRequestHtmlFields()}
        </form>

        <script type="text/javascript">
            window.onload = function() {
                document.payzen_form.submit();
            };
        </script>
    </body>
</html>
EOT;

        die('<div style="text-align: center;">' . $msg . '</div>');
    }

    public function finalize(
        AsyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): void {
        // Load gateway response.
        $fromServer = $request->request->get('vads_hash') !== null;
        $params = (($request->getMethod() === Request::METHOD_POST) || $fromServer) ? $request->request : $request->query;

        $contextInfo = explode('&', (string) $params->get('vads_order_info'));

        // Restore transaction ID.
        if (! empty($contextInfo) && (sizeof($contextInfo) === 2)) {
            //Read transaction
            $orderTransactionId = substr($contextInfo[0], strlen('order_transaction_id='));
            $salesChannelId = substr($contextInfo[1], strlen('sales_channel_id='));
        }

        //Init session.
        $session = ($request->hasSession()) ? $request->getSession() : new Session();
        if (! $session->isStarted()) {
            $session->start();
        }

        $session->set('payzenGoingIntoProductionInfo', false);
        $session->set('payzenCheckUrlWarn', false);
        $session->set('payzenTechError', false);

        $session->set('payzenIsCancelledPayment', false);
        $session->set('payzenIsPaymentError', false);

        $payzenResponse = new PayzenResponse(
            $params->all(),
            $this->getConfig('ctx_mode', $salesChannelId),
            $this->getConfig('key_test', $salesChannelId),
            $this->getConfig('key_prod', $salesChannelId),
            $this->getConfig('sign_algo', $salesChannelId)
        );

        // Check the authenticity of the request.
        if (! $payzenResponse->isAuthentified()) {
            $ip = $request->getClientIp();

            $this->logger->error("{$ip} tries to access payment_payzen/process page without valid signature with parameters: " . print_r($params->all(), true));
            $this->logger->error('Signature algorithm selected in module settings must be the same as one selected in PayZen Back Office.');

            if ($fromServer) {
                $this->logger->info('IPN URL PROCESS END.');
                die($payzenResponse->getOutputForGateway('auth_fail'));
            } else {
                $session->set('payzenTechError', true);

                $this->logger->info('RETURN URL PROCESS END.');
                throw new AsyncPaymentFinalizeException($orderTransactionId, $this->translator->trans('payzenPaymentFatal'));
            }
        }

        // Retrieve order info from DB.
        $orderId = $payzenResponse->get('order_id');;
        $order = $this->orderService->filterOrderNumber($orderId, $salesChannelContext->getContext());

        if (empty($order)) {
            $this->logger->error("Error: order #$orderId not found in database.");

            if ($fromServer) {
                $this->logger->info('IPN URL PROCESS END.');
                die($payzenResponse->getOutputForGateway('order_not_found'));
            } else {
                $session->set('payzenTechError', true);

                $this->logger->info('RETURN URL PROCESS END.');
                throw new AsyncPaymentFinalizeException($orderTransactionId, $this->translator->trans('payzenPaymentFatal'));
            }
        }

        // Get current transaction status.
        $orderTransaction = $transaction->getOrderTransaction();
        $currentPaymentStatus = $orderTransaction->getStateMachineState()->getTechnicalName();

        if ($this->isNewOrderTransaction($orderTransaction, $payzenResponse)) {
            // Order not processed yet, update transaction status.
            $context = $salesChannelContext->getContext();
            $newPaymentStatus = $this->getNewOrderPaymentStatus($payzenResponse);

            if (($newPaymentStatus !== OrderTransactionStates::STATE_CANCELLED) && ($currentPaymentStatus === OrderTransactionStates::STATE_CANCELLED)) {
                $this->transactionStateHandler->reopen($transaction->getOrderTransaction()->getId(), $context);
            }

            // Payment completed, set transaction status the status configured in plugin backend.
            switch ($newPaymentStatus) {
                case OrderTransactionStates::STATE_PAID:
                    $this->transactionStateHandler->pay($transaction->getOrderTransaction()->getId(), $context);
                    break;

                case OrderTransactionStates::STATE_CANCELLED:
                    if ($currentPaymentStatus !== OrderTransactionStates::STATE_CANCELLED) {
                        $this->transactionStateHandler->cancel($transaction->getOrderTransaction()->getId(), $context);
                    }
                    break;

                case OrderTransactionStates::STATE_PARTIALLY_PAID:
                    $this->transactionStateHandler->payPartially($transaction->getOrderTransaction()->getId(), $context);
                    break;

                case OrderTransactionStates::STATE_REMINDED:
                    $this->transactionStateHandler->remind($transaction->getOrderTransaction()->getId(), $context);
                    break;
            }

            // Update order status.
            $this->orderService->saveOrderStatus($context, $transaction->getOrderTransaction()->getOrderId(), $this->getNewOrderTransition($payzenResponse));

            // Update transaction data.
            if (! $payzenResponse->isCancelledPayment()) {
                $this->saveTransactionData($transaction, $payzenResponse, $salesChannelContext->getContext());
            }

            if ($fromServer) {
                if ($payzenResponse->isAcceptedPayment()) {
                    $this->logger->info("Payment processed successfully by IPN URL call for order #$orderId.");
                    $msg = 'payment_ok';
                } else {
                    $this->logger->info("Payment failed or cancelled for order #$orderId. {$payzenResponse->getLogMessage()}");
                    $msg = 'payment_ko';
                }

                $this->logger->info('IPN URL PROCESS END.');
                die($payzenResponse->getOutputForGateway($msg));
            } else {
                if ($payzenResponse->isAcceptedPayment()) {
                    $this->logger->info("Warning! IPN URL call has not worked. Payment completed by return URL call for order #$orderId.");
                } else {
                    $this->logger->info("Payment failed or cancelled for order #$orderId. {$payzenResponse->getLogMessage()}");
                    if ($payzenResponse->isCancelledPayment()) {
                        $session->set('payzenIsCancelledPayment', true);
                    } else {
                        $session->set('payzenIsPaymentError', true);
                    }
                }

                // Display a warning about check URL not working.
                if ($payzenResponse->isAcceptedPayment() && ($this->getConfig('ctx_mode', $salesChannelId) === 'TEST')) {
                    $session->set('payzenCheckUrlWarn', true);
                }

                $this->logger->info('RETURN URL PROCESS END.');
            }
        } else {
            // Order already processed.
            $this->logger->info("Order #$orderId is already saved.");

            // Get status configured in module backend.
            $successStatus = $this->getConfig('payment_status_on_success', $salesChannelId);

            if ($currentPaymentStatus === $successStatus) {
                if ($payzenResponse->isAcceptedPayment()) {
                    $this->logger->info("Payment successful confirmed for order #$orderId.");
                    if ($fromServer) {
                        $this->logger->info('IPN URL PROCESS END.');
                        die($payzenResponse->getOutputForGateway('payment_ok_already_done'));
                    } else {
                        $this->logger->info('RETURN URL PROCESS END.');
                    }
                } else {
                    $this->logger->info("Error! Invalid payment result received for already saved order #$orderId. Payment result: {$payzenResponse->getTransStatus()}, Order status: {$currentPaymentStatus}.");
                    if ($fromServer) {
                        $this->logger->info('IPN URL PROCESS END.');
                        die($payzenResponse->getOutputForGateway('payment_ko_on_order_ok'));
                    } else {
                        $session->set('payzenTechError', true);

                        $this->logger->info('RETURN URL PROCESS END.');
                        throw new AsyncPaymentFinalizeException($transaction->getOrderTransaction()->getId(), $this->translator->trans('payzenPaymentFatal'));
                    }
                }
            } else {
                $this->logger->info("Payment failed or cancelled confirmed for order #$orderId.");

                if ($fromServer) {
                    $this->logger->info('IPN URL PROCESS END.');
                    die($payzenResponse->getOutputForGateway('payment_ko_already_done'));
                } else {
                    $this->logger->info('RETURN URL PROCESS END.');

                    if ($payzenResponse->isCancelledPayment()) {
                        $session->set('payzenIsCancelledPayment', true);
                    } else {
                        $session->set('payzenIsPaymentError', true);
                    }
                }
            }
        }

        if (! $fromServer && ($this->getConfig('ctx_mode', $salesChannelId) === 'TEST')
            && PayzenTools::$pluginFeatures['prodfaq'] && $payzenResponse->isAcceptedPayment()) {
            $session->set('payzenGoingIntoProductionInfo', true);
        }
    }

    /**
     * Check if the order needs to be processed.
     *
     * @param OrderTransactionEntity $orderTransaction
     * @param PayzenResponse $payzenResponse
     * @return boolean
     */
    private function isNewOrderTransaction(OrderTransactionEntity $orderTransaction, PayzenResponse $payzenResponse)
    {
        $currentPaymentStatus = $orderTransaction->getStateMachineState()->getTechnicalName();

        if ($currentPaymentStatus === OrderTransactionStates::STATE_OPEN) {
            return true;
        }

        if ($currentPaymentStatus === OrderTransactionStates::STATE_CANCELLED) {
            $customFields = $orderTransaction->getCustomFields() ?? [];
            if (! empty($customFields)) {
                if ((array_key_exists(PayzenCustomFieldInstaller::TRANSACTION_UUID, $customFields))
                    && ($customFields[PayzenCustomFieldInstaller::TRANSACTION_UUID] !== $payzenResponse->get('trans_uuid'))) {
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
     * Return the Shopware payment status corresponding to gateway payment result.
     *
     * @param PayzenResponse $payzenResponse
     * @param string $salesChannelId
     * @return string
     */
    private function getNewOrderPaymentStatus(PayzenResponse $payzenResponse, ?string $salesChannelId = null)
    {
        $status = $payzenResponse->get('trans_status');

        if (in_array($status, PayzenApi::getSuccessStatuses())) {
            return $this->getConfig('payment_status_on_success', $salesChannelId);
        } elseif (in_array($status, PayzenApi::getPendingStatuses())) {
            return OrderTransactionStates::STATE_OPEN; // Open.
        } else {
            // Case of cancelled and failed payments.
            return OrderTransactionStates::STATE_CANCELLED;
        }
    }

    /**
     * Return the Shopware order status corresponding to gateway payment result.
     *
     * @param PayzenResponse $payzenResponse
     * @return string
     */
    private function getNewOrderTransition(PayzenResponse $payzenResponse)
    {
        $status = $payzenResponse->get('trans_status');

        if (in_array($status, PayzenApi::getSuccessStatuses()) || in_array($status, PayzenApi::getPendingStatuses())) {
            return StateMachineTransitionActions::ACTION_PROCESS;
        } else {
            // Case of cancelled and failed payments.
            return StateMachineTransitionActions::ACTION_CANCEL;
        }
    }

    /**
     * Save transaction data.
     *
     * @param PayzenResponse $payzenResponse
     * @return string
     */
    public function saveTransactionData(AsyncPaymentTransactionStruct $transaction, PayzenResponse $payzenResponse, Context $context): void
    {
        // Save payment details.
        $expiry = '';
        if ($payzenResponse->get('expiry_month') && $payzenResponse->get('expiry_year')) {
            $expiry = str_pad($payzenResponse->get('expiry_month'), 2, '0', STR_PAD_LEFT) . ' / ' . $payzenResponse->get('expiry_year');
        }

        $cardBrand = $payzenResponse->get('card_brand');
        if ($payzenResponse->get('brand_management')) {
            $brandInfo = json_decode($payzenResponse->get('brand_management'));

            if (isset($brandInfo->userChoice) && $brandInfo->userChoice) {
                $brandChoice = $this->translator->trans('payzenUserChoice');
            } else {
                $brandChoice = $this->translator->trans('payzenDefaultChoice');
            }

            $cardBrand .= ' (' . $brandChoice . ')';
        }

        $data = [
            PayzenCustomFieldInstaller::TRANSACTION_ID => $payzenResponse->get('trans_id'),
            PayzenCustomFieldInstaller::TRANSACTION_UUID => $payzenResponse->get('trans_uuid'),
            PayzenCustomFieldInstaller::TRANSACTION_TYPE => $payzenResponse->get('operation_type'),
            PayzenCustomFieldInstaller::TRANSACTION_MESSAGE => $payzenResponse->getMessage(),
            PayzenCustomFieldInstaller::MEANS_OF_PAYMENT => $cardBrand,
            PayzenCustomFieldInstaller::CARD_NUMBER => $payzenResponse->get('card_number'),
            PayzenCustomFieldInstaller::CARD_EXPIRATION_DATE => $expiry
        ];

        $customFields = $transaction->getOrderTransaction()->getCustomFields() ?? [];
        $customFields = array_merge($customFields, $data);

        $update = [
            'id' => $transaction->getOrderTransaction()->getId(),
            'customFields' => $customFields,
        ];

        $transaction->getOrderTransaction()->setCustomFields($customFields);

        $this->transactionRepository->update([$update], $context);
    }
}
