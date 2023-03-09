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

use Lyranetwork\Lyra\PaymentMethods\Standard;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

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
     * @var EntityRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        AccountService $accountService,
        RouterInterface $router,
        Standard $standardPayment,
        EntityRepositoryInterface $transactionRepository,
        LoggerInterface $logger
    ) {
        $this->accountService = $accountService;
        $this->router = $router;
        $this->standardPayment = $standardPayment;
        $this->transactionRepository = $transactionRepository;
        $this->logger = $logger;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/lyra/finalize", defaults={"csrf_protected"=false, "auth_required"=false}, name="lyra_finalize", methods={"GET", "POST"})
     */
    public function finalize(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $params = (($request->getMethod() === Request::METHOD_POST)) ? $request->request : $request->query;

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
            $orderTransaction = $this->transactionRepository->search($criteria, Context::createDefaultContext())->first();

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
    
    /**
     * @param RouterInterface $router
     * @return string
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
