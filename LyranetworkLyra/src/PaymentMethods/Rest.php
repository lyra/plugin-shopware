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

use Lyranetwork\Lyra\Installer\CustomFieldInstaller;
use Lyranetwork\Lyra\Sdk\RestData;
use Lyranetwork\Lyra\Sdk\Tools;
use Lyranetwork\Lyra\Service\ConfigService;
use Lyranetwork\Lyra\Sdk\Form\Response as LyraResponse;
use Lyranetwork\Lyra\Sdk\Rest\Api as LyraRest;
use Lyranetwork\Lyra\Sdk\Form\Api as LyraApi;
use Lyranetwork\Lyra\Service\OrderService;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\Translation\TranslatorInterface;

use Psr\Log\LoggerInterface;

class Rest implements SynchronousPaymentHandlerInterface
{
    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var RestData
     */
    private $restData;

    /**
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;

    /**
     * @var EntityRepository
     */
    private $transactionRepository;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var array
     */
    private $paymentResult;

    public function __construct(
        AccountService $accountService,
        RouterInterface $router,
        LoggerInterface $logger,
        ConfigService $configService,
        TranslatorInterface $translator,
        OrderService $orderService,
        RestData $restData,
        OrderTransactionStateHandler $transactionStateHandler,
        EntityRepository $transactionRepository,
        RequestStack $requestStack
    ) {
        $this->accountService = $accountService;
        $this->router = $router;
        $this->logger = $logger;
        $this->configService = $configService;
        $this->translator = $translator;
        $this->orderService = $orderService;
        $this->restData = $restData;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->transactionRepository = $transactionRepository;
        $this->requestStack = $requestStack;
        $this->paymentResult = array(
            'lyraIsCancelledPayment' => false,
            'lyraIsPaymentError' => false
        );
    }

    public function pay(
        SyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): void {
        $request = $this->requestStack->getMainRequest();
        $fromServer = $dataBag->get('fromServer') !== null && $dataBag->get('fromServer');
        $order = $transaction->getOrder();

        $response = $fromServer ? $dataBag->all('lyraResponse') : json_decode($dataBag->get('lyraResponse'), true);
        $params = $this->restData->convertRestResult($response['clientAnswer']);

        // Context information is passed through vads_ext_info_*.
        $orderTransactionId = $transaction->getOrderTransaction()->getId();
        $salesChannelId = $order->getSalesChannelId();
        $orderId = $order->getOrderNumber();

        // Init session.
        $session = ($request->hasSession()) ? $request->getSession() : new Session();
        if (! $session->isStarted()) {
            $session->start();
        }

        $session->set('lyraGoingIntoProductionInfo', false);
        $session->set('lyraCheckUrlWarn', false);
        $session->set('lyraTechError', false);

        $lyraResponse = new LyraResponse(
            $params,
            $this->getConfig('ctx_mode', $salesChannelId),
            $this->getConfig('key_test', $salesChannelId),
            $this->getConfig('key_prod', $salesChannelId),
            $this->getConfig('sign_algo', $salesChannelId)
        );

        // Check the authenticity of the request.
        $key = $fromServer ? $this->restData->getPrivateKey() : $this->restData->getReturnKey();
        if (! $this->restData->checkResponseHash($response, $key)) {
            $this->logger->error("Tried to access payment_lyra/process page without valid signature.");
            $this->logger->error('Signature algorithm selected in module settings must be the same as one selected in Lyra Expert Back Office.');

            if ($fromServer) {
                $this->logger->info('IPN URL PROCESS END.');
                echo $lyraResponse->getOutputForGateway('auth_fail');
                return;
            } else {
                $session->set('lyraTechError', true);

                $this->logger->info('RETURN URL PROCESS END.');
                throw new SyncPaymentProcessException($orderTransactionId, $this->translator->trans('lyraPaymentFatal'));
            }
        }

        // Retrieve order info from DB.
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
                throw new SyncPaymentProcessException($orderTransactionId, $this->translator->trans('lyraPaymentFatal'));
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
                        throw new SyncPaymentProcessException($transaction->getOrderTransaction()->getId(), $this->translator->trans('lyraPaymentFatal'));
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

        $customerEmail = $params['vads_cust_email'];
        $this->accountService->login($customerEmail, $salesChannelContext, true);

        if ($this->paymentResult['lyraIsPaymentError'] || $this->paymentResult['lyraIsCancelledPayment']) {
            $finishUrl = $this->getAccountOrderPage($this->router);
            $session->set('lyraPaymentCancel', $this->paymentResult['lyraIsCancelledPayment']);
            $session->set('lyraIsPaymentError', $this->paymentResult['lyraIsPaymentError']);
            header('Location: ' . $finishUrl);
            exit();
        }

        if (! $fromServer && ($this->getConfig('ctx_mode', $salesChannelId) === 'TEST')
            && Tools::$pluginFeatures['prodfaq'] && $lyraResponse->isAcceptedPayment()) {
            $session->set('lyraGoingIntoProductionInfo', true);
        }
    }

    /**
     * @param RouterInterface $router
     * @return string
     */
    public function getAccountOrderPage(RouterInterface $router): string
    {
        return $router->generate('frontend.account.order.page', [], $router::ABSOLUTE_URL);
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

    public function saveTransactionData(SyncPaymentTransactionStruct $transaction, LyraResponse $lyraResponse, Context $context): void
    {
        // Save payment details.
        $expiry = '';
        if ($lyraResponse->get('expiry_month') && $lyraResponse->get('expiry_year')) {
            $expiry = str_pad('' . $lyraResponse->get('expiry_month'), 2, '0', STR_PAD_LEFT) . ' / ' . $lyraResponse->get('expiry_year');
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

        $orderId = $lyraResponse->get('order_id');

        $data = [
            CustomFieldInstaller::TRANSACTION_ID => $lyraResponse->get('trans_id'),
            CustomFieldInstaller::TRANSACTION_UUID => $lyraResponse->get('trans_uuid'),
            CustomFieldInstaller::TRANSACTION_TYPE => $lyraResponse->get('operation_type'),
            CustomFieldInstaller::TRANSACTION_STATUS => $lyraResponse->get('trans_status'),
            CustomFieldInstaller::TRANSACTION_MESSAGE => $lyraResponse->getMessage(),
            CustomFieldInstaller::MEANS_OF_PAYMENT => $cardBrand,
            CustomFieldInstaller::CARD_NUMBER => $lyraResponse->get('card_number'),
            CustomFieldInstaller::CARD_EXPIRATION_DATE => $expiry,
            CustomFieldInstaller::ORDER_ID => $orderId
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

    public function finalizePayment(
        SyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext,
        bool $fromServer = true)
    {
        $params = ($request->getMethod() === Request::METHOD_POST) ? $request->request : $request->query;
        $data = [
            'clientAnswer' => json_decode($params->get('kr-answer'), true),
            'hashAlgorithm' => $params->get('kr-hash-algorithm'),
            'hash' => $params->get('kr-hash'),
            'rawClientAnswer' => $params->get('kr-answer')
        ];

        $this->pay($transaction, new RequestDataBag(['lyraResponse' => $data, 'fromServer' => $fromServer]), $salesChannelContext);

        return $this->paymentResult;
    }
}