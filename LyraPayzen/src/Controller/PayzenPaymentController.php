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
namespace LyraPayment\Payzen\Controller;

use LyraPayment\Payzen\PaymentMethods\PaymentPayzen;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\Response;

class PayzenPaymentController extends StorefrontController
{
    /**
     * @var PaymentPayzen
     */
    private $paymentPayzen;

    /**
     * @var EntityRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        PaymentPayzen $paymentPayzen,
        EntityRepositoryInterface $transactionRepository,
        LoggerInterface $logger
    ) {
        $this->paymentPayzen = $paymentPayzen;
        $this->transactionRepository = $transactionRepository;
        $this->logger = $logger;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/PaymentPayzen/finalize", defaults={"csrf_protected"=false, "auth_required"=false}, name="payzen_finalize", methods={"POST"})
     */
    public function finalize(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $params = $request->request;
        $contextInfo = explode('&', (string) $params->get('vads_order_info'));

        // Restore payment transaction data for an IPN call.
        if ($params->get('vads_hash') && ! empty($contextInfo) && (sizeof($contextInfo) === 2)) {
            // Read transaction.
            $orderTransactionId = substr($contextInfo[0], strlen('order_transaction_id='));

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
                die('<span style="display:none">KO-Order not found.' . "\n" . '</span>');
            }

            /**
             * @var null|string $returnUrl
             */
            $returnUrl = (string) $params->get('vads_url_return');

            $transaction = new AsyncPaymentTransactionStruct($orderTransaction, $order, $returnUrl);

            $this->paymentPayzen->finalize($transaction, $request, $salesChannelContext);
        }

        die();
    }
}
