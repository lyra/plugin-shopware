<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);
namespace Lyranetwork\Lyra\Service;

use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Lyranetwork\Lyra\PaymentMethods\Standard;

class PaymentMethodService
{
    /**
     * @var EntityRepository
     */
    private $paymentRepository;

    /**
     * @var EntityRepository
     */
    private $salesChannelRepository;

    public function __construct(
        EntityRepository $paymentRepository,
        EntityRepository $salesChannelRepository
    ) {
        $this->paymentRepository = $paymentRepository;
        $this->salesChannelRepository = $salesChannelRepository;
    }

    public function getPaymentMethodId(Context $context, $paymentMethodClass): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('handlerIdentifier', $paymentMethodClass));

        return $this->paymentRepository->searchIds($criteria, $context)->firstId();
    }

    public function isPaymentMethodInSalesChannel(SalesChannelContext $salesChannelContext, $paymentMethodClass): bool
    {
        $context = $salesChannelContext->getContext();

        $paymentMethodId = $this->getPaymentMethodId($context, $paymentMethodClass);
        if (! $paymentMethodId) {
            return false;
        }

        $paymentMethods = $this->getSalesChannelPaymentMethods($salesChannelContext->getSalesChannel(), $context);
        if (! $paymentMethods) {
            return false;
        }

        if ($paymentMethods->get($paymentMethodId) instanceof PaymentMethodEntity) {
            return true;
        }

        return false;
    }

    private function getSalesChannelPaymentMethods(SalesChannelEntity $salesChannelEntity, Context $context): ?PaymentMethodCollection
    {
        $salesChannelId = $salesChannelEntity->getId();
        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('paymentMethods');

        /**
         * @var SalesChannelEntity|null $result
         */
        $result = $this->salesChannelRepository->search($criteria, $context)->get($salesChannelId);

        if (! $result) {
            return null;
        }

        return $result->getPaymentMethods();
    }
}