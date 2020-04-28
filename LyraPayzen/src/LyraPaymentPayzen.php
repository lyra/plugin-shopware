<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);
namespace LyraPayment\Payzen;

use LyraPayment\Payzen\PaymentMethods\PaymentPayzen;
use LyraPayment\Payzen\Installer\PayzenConfigInstaller;
use LyraPayment\Payzen\Installer\PayzenCustomFieldInstaller;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class LyraPaymentPayzen extends Plugin
{
    public function install(InstallContext $context): void
    {
        $path = $this->getBasePath();
        (new PayzenConfigInstaller($this->container, $path))->install($context);

        $this->addPaymentMethod($context->getContext());
        (new PayzenCustomFieldInstaller($this->container))->install($context);
    }

    public function uninstall(UninstallContext $context): void
    {
        // Only set the payment method as inactive when uninstalling. Removing the payment method would
        // cause data consistency issues, since the payment method might have been used in several orders.
        $this->setPaymentMethodIsActive(false, $context->getContext());
    }

    public function activate(ActivateContext $context): void
    {
        $this->setPaymentMethodIsActive(true, $context->getContext());
        (new PayzenCustomFieldInstaller($this->container))->activate($context);

        parent::activate($context);
    }

    public function deactivate(DeactivateContext $context): void
    {
        $this->setPaymentMethodIsActive(false, $context->getContext());
        (new PayzenCustomFieldInstaller($this->container))->deactivate($context);

        parent::deactivate($context);
    }

    private function addPaymentMethod(Context $context): void
    {
        $paymentMethodExists = $this->getPaymentMethodId();

        // Payment method exists already, no need to continue here.
        if ($paymentMethodExists) {
            return;
        }

        /**
         * @var PluginIdProvider $pluginIdProvider
         */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass('LyraPayment\\Payzen\\LyraPaymentPayzen', $context);

        $data = [
             // Payment handler will be selected by the identifier.
            'handlerIdentifier' => PaymentPayzen::class,
            'name' => 'PayZen',
            'description' => 'PayZen payment',
            'pluginId' => $pluginId
        ];

        /**
         * @var EntityRepositoryInterface $paymentRepository
         */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->create([$data], $context);
    }

    private function setPaymentMethodIsActive(bool $active, Context $context): void
    {
        /**
         * @var EntityRepositoryInterface $paymentRepository
         */
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethodId = $this->getPaymentMethodId();

        // Payment does not even exist, so nothing to (de-)activate here.
        if (! $paymentMethodId) {
            return;
        }

        $paymentMethod = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];

        $paymentRepository->update([$paymentMethod], $context);
    }

    private function getPaymentMethodId(): ?string
    {
        /**
         * @var EntityRepositoryInterface $paymentRepository
         */
        $paymentRepository = $this->container->get('payment_method.repository');

        // Fetch ID for update.
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', PaymentPayzen::class));
        $paymentIds = $paymentRepository->searchIds($paymentCriteria, Context::createDefaultContext());

        if ($paymentIds->getTotal() === 0) {
            return null;
        }

        return $paymentIds->getIds()[0];
    }

    public function getViewPaths(): array
    {
        $viewPaths = parent::getViewPaths();
        $viewPaths[] = 'Resources/views/storefront';

        return $viewPaths;
    }
}
