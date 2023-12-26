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
namespace Lyranetwork\Lyra\Installer;

use Lyranetwork\Lyra\PaymentMethods\Standard;

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PaymentMethodInstaller
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var PluginIdProvider
     */
    private $pluginIdProvider;

    /**
     * @var EntityRepository
     */
    private $paymentMethodRepository;

    /**
     * @var EntityRepository
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepository
     */
    private $paymentMethodSalesChannelRepository;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
        $this->pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $this->paymentMethodRepository = $this->container->get('payment_method.repository');
        $this->salesChannelRepository = $this->container->get('sales_channel.repository');
        $this->paymentMethodSalesChannelRepository = $this->container->get('sales_channel_payment_method.repository');
    }

    public function install(InstallContext $installContext): void
    {
        $context = $installContext->getContext();
        $paymentMethodExists = $this->getPaymentMethodId($context);

        // Payment method exists already, no need to continue here.
        if ($paymentMethodExists) {
            return;
        }

        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass('Lyranetwork\\Lyra\\LyranetworkLyra', $context);

        $data = [
             // Payment handler will be selected by the identifier.
            'handlerIdentifier' => Standard::class,
            'name' => 'Lyra Collect',
            'description' => 'Lyra Collect payment',
            'pluginId' => $pluginId
        ];

        $this->paymentMethodRepository->create([$data], $context);

        $this->enablePaymentMethodForSaleChannels($this->getPaymentMethodId($context), $context);
    }

    public function update(UpdateContext $context): void
    {
    }

    public function uninstall(UninstallContext $context): void
    {
        $this->setPaymentMethodStatus(false, $context->getContext());
    }

    public function activate(ActivateContext $context): void
    {
        $this->setPaymentMethodStatus(true, $context->getContext());
    }

    public function deactivate(DeactivateContext $context): void
    {
        $this->setPaymentMethodStatus(false, $context->getContext());
    }

    private function getPaymentMethodId(Context $context): ?string
    {
        // Fetch ID for update.
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', Standard::class));
        $paymentIds = $this->paymentMethodRepository->searchIds($paymentCriteria, $context);

        if ($paymentIds->getTotal() === 0) {
            return null;
        }

        return $paymentIds->getIds()[0];
    }

    private function setPaymentMethodStatus(bool $active, Context $context): void
    {
        $paymentMethodId = $this->getPaymentMethodId($context);

        // Payment does not even exist, so nothing to (de-)activate here.
        if (! $paymentMethodId) {
            return;
        }

        $paymentMethod = [
            'id' => $paymentMethodId,
            'active' => $active
        ];

        $this->paymentMethodRepository->update([$paymentMethod], $context);
    }

    private function enablePaymentMethodForSaleChannels(string $paymentMethodId, Context $context): void
    {
        $channels = $this->salesChannelRepository->searchIds(new Criteria(), $context);

        foreach ($channels->getIds() as $channel) {
            $data = [
                'salesChannelId'  => $channel,
                'paymentMethodId' => $paymentMethodId,
            ];

            $this->paymentMethodSalesChannelRepository->upsert([$data], $context);
        }
    }
}