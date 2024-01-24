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
namespace Lyranetwork\Lyra\Controller;

use Lyranetwork\Lyra\PaymentMethods\Rest;
use Lyranetwork\Lyra\PaymentMethods\Standard;

use Lyranetwork\Lyra\Sdk\RestData;
use Lyranetwork\Lyra\Sdk\Tools;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\SyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class PaymentController extends StorefrontController
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
     * @var Standard
     */
    private $standardPayment;

    /**
     * @var EntityRepository
     */
    private $transactionRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var RestData
     */
    private $restData;

     /**
     * @var Rest
     */
    private $restPayment;

    public function __construct(
        AccountService $accountService,
        RouterInterface $router,
        Standard $standardPayment,
        EntityRepository $transactionRepository,
        LoggerInterface $logger,
        RestData $restData,
        Rest $restPayment
    ) {
        $this->accountService = $accountService;
        $this->router = $router;
        $this->standardPayment = $standardPayment;
        $this->transactionRepository = $transactionRepository;
        $this->logger = $logger;
        $this->restData = $restData;
        $this->restPayment = $restPayment;
    }

    #[Route(path: '/lyra/finalize', defaults: ['csrf_protected' => false, 'auth_required' => false], name: 'lyra_finalize', methods: ['GET', 'POST'])]
    public function finalize(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $params = ($request->getMethod() === Request::METHOD_POST) ? $request->request : $request->query;

        // Context information is passed through vads_ext_info_* since v3.0.0.
        $orderTransactionId = (string) $params->get('vads_ext_info_order_transaction_id');
        $salesChannelId = (string) $params->get('vads_ext_info_sales_channel_id');

        if (empty($orderTransactionId) || empty($salesChannelId)) {
            // Search with vads_order_info for old versions.
            $contextInfo = explode('&', (string) $params->get('vads_order_info'));

            if (! empty($contextInfo) && (sizeof($contextInfo) === 2)) {
                $orderTransactionId = substr($contextInfo[0], strlen('order_transaction_id='));
                $salesChannelId = substr($contextInfo[1], strlen('sales_channel_id='));
            }
        }

        // Restore payment transaction data for an IPN call.
        if (! empty($orderTransactionId) && ! empty($salesChannelId)) {
            // Read transaction.
            $criteria = new Criteria([$orderTransactionId]);
            $criteria->addAssociation('order');
            $criteria->addAssociation('paymentMethod');

            /**
             * @var null|OrderTransactionEntity $orderTransaction
             */
            $orderTransaction = $this->transactionRepository->search($criteria, $salesChannelContext->getContext())->first();

            if ($orderTransaction) {
                /**
                 * @var null|OrderEntity $order
                 */
                $order = $orderTransaction->getOrder();
            }

            if (! isset($order) || ! $order) {
                echo '<span style="display:none">KO-Order not found.' . "\n" . '</span>';
            } else {
                /**
                 * @var null|string $returnUrl
                 */
                $returnUrl = (string) $params->get('vads_url_return');

                $transaction = new AsyncPaymentTransactionStruct($orderTransaction, $order, $returnUrl);

                $messages = $this->standardPayment->finalizePayment($transaction, $request, $salesChannelContext);

                // Buyer return in POST mode.
                if (! $params->get('vads_hash')) {
                    $customerEmail = $params->get('vads_cust_email');
                    $this->accountService->login($customerEmail, $salesChannelContext, true);

                    if (($messages['lyraIsPaymentError'] == true) || ($messages['lyraIsCancelledPayment'] == true)) {
                        $finishUrl = $this->getAccountOrderPage($this->router);
                        $this->addFlashMessages($messages);
                    } else {
                        $orderId = $order->getId();
                        $finishUrl = $this->getCheckoutFinishPage($orderId, $this->router);
                    }

                    return new RedirectResponse($finishUrl);
                }
            }
        }

        return new Response();
    }

    #[Route(path: '/lyra/finalizeRest', name: 'lyra_finalize_rest', defaults: ['csrf_protected' => false, 'auth_required' => false], methods: ['GET', 'POST'])]
    public function finalizeRest(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $params = ($request->getMethod() === Request::METHOD_POST) ? $request->request : $request->query;
        if (Tools::checkRestIpnValidity($params)) {
            $answer = json_decode($params->get('kr-answer'), true);

            if (! is_array($answer)) {
                $this->logger->error('Invalid REST IPN request received. Content of kr-answer: ' . json_encode($params->get('kr-answer')));
                echo '<span style="display:none">KO-Invalid IPN request received.' . "\n" . '</span>';
            } else {
                $data = $this->restData->convertRestResult($answer, false);

                // Context information is passed through vads_ext_info_* since v3.0.0.
                $orderId = (string) $data['vads_order_id'];
                $salesChannelId = (string) $data['vads_ext_info_sales_channel_id'];

                if (empty($orderId) || empty($salesChannelId)) {
                    // Search with vads_order_info for old versions.
                    $contextInfo = explode('&', (string) $data['vads_order_info']);

                    if (! empty($contextInfo) && (sizeof($contextInfo) === 2)) {
                        $orderId = substr($contextInfo[0], strlen('order_id='));
                        $salesChannelId = substr($contextInfo[1], strlen('sales_channel_id='));
                    }
                }

                // Restore payment transaction data for an IPN call.
                if (! empty($orderId) && ! empty($salesChannelId)) {
                    // Read transaction.
                    $criteria = new Criteria();
                    $criteria->addFilter(new ContainsFilter('customFields.lyra_order_id', $orderId));
                    $criteria->addAssociation('customFields');
                    $criteria->addAssociation('order');
                    $criteria->addAssociation('paymentMethod');

                    /**
                     * @var null|OrderTransactionEntity $orderTransaction
                     */
                    $orderTransaction = $this->transactionRepository->search($criteria, $salesChannelContext->getContext())->first();
                    if ($orderTransaction) {
                        /**
                         * @var null|OrderEntity $order
                         */
                        $order = $orderTransaction->getOrder();
                    }

                    if (! isset($order) || ! $order) {
                        $this->logger->error('Synchronous payment, IPN ignored.');
                        echo '<span style="display:none">Synchronous payment, IPN ignored.' . "\n" . '</span>';
                    } else {
                        $transaction = new SyncPaymentTransactionStruct($orderTransaction, $order);
                        $this->restPayment->finalizePayment($transaction, $request, $salesChannelContext);
                    }
                }
            }
        } else {
            $this->logger->error('Invalid IPN request received. Content: ' . print_r($params, true));
            echo '<span style="display:none">KO-Invalid IPN request received.' . "\n" . '</span>';
        }

        return new Response();
    }

    /**
     * @param RouterInterface $router
     */
    public function addFlashMessages(array $messages)
    {
        if (array_key_exists('lyraIsCancelledPayment', $messages) && ($messages['lyraIsCancelledPayment'] == true)) {
            $this->addFlash('warning', $this->trans('lyraPaymentCancel'));
        }

        if (array_key_exists('lyraIsPaymentError', $messages) && ($messages['lyraIsPaymentError'] == true)) {
            $this->addFlash('warning', $this->trans('lyraPaymentError'));
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
     * @param string $orderId
     * @param RouterInterface $router
     * @return string
     */
    public function getCheckoutFinishPage(string $orderId, RouterInterface $router): string
    {
        return $router->generate('frontend.checkout.finish.page', ['orderId' => $orderId, ], $router::ABSOLUTE_URL);
    }
}
