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

use Lyranetwork\Lyra\Sdk\Tools;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Defaults;

class ConfigInstaller
{
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Array
     */
    private $translations;

    /**
     * @var Array
     */
    private $defaultValues;

    public function __construct(ContainerInterface $container, Context $context)
    {
        $this->container = $container;
        $this->systemConfigService = $this->container->get(SystemConfigService::class);
        $this->initTranslations();
        $this->initDefaults($context);
    }

    private function initDefaults(Context $context)
    {
        $lang = Tools::getDefault('LANGUAGE');

        $this->defaultValues = [
            'lyraDevelopedBy' => 'Lyra Network (https://www.lyra.com/)',
            'lyraContactEmail' => Tools::getDefault('SUPPORT_EMAIL'),
            'lyraModuleVersion' => Tools::getDefault('PLUGIN_VERSION'),
            'lyraGatewayVersion' => Tools::getDefault('GATEWAY_VERSION'),
            'lyraSiteId' => Tools::getDefault('SITE_ID'),
            'lyraKeyTest' => Tools::getDefault('KEY_TEST'),
            'lyraKeyProd' => Tools::getDefault('KEY_PROD'),
            'lyraCtxMode' => Tools::getDefault('CTX_MODE'),
            'lyraSignAlgo' => Tools::getDefault('SIGN_ALGO'),
            'lyraCheckUrl' => $this->getBaseUrl($context) . '/lyra/finalize',
            'lyraPlatformUrl' => Tools::getDefault('GATEWAY_URL'),
            'lyraLanguage' => Tools::getDefault('LANGUAGE'),
            'lyraRedirectSuccessTimeout' => '5',
            'lyraValidationMode' => '',
            'lyraRedirectSuccessMessage' => $this->translations[$lang]['lyraRedirectSuccessMessageDefault'],
            'lyraRedirectErrorTimeout' => '5',
            'lyraRedirectErrorMessage' => $this->translations[$lang]['lyraRedirectErrorMessageDefault'],
            'lyraReturnMode' => 'GET',
            'lyraPaymentStatusOnSuccess' => 'paid',
            'lyraOrderPlacedFlowEnabled' => $this->getShopwareOrderPlacedFlowActive($context)
        ];
    }

    private function initTranslations()
    {
        $this->translations = [
            'en' => [
                'lyraRedirectSuccessMessageDefault' => 'Redirection to shop in a few seconds...',
                'lyraRedirectErrorMessageDefault' => 'Redirection to shop in a few seconds...'
            ],
            'de' => [
                'lyraRedirectSuccessMessageDefault' => 'Weiterleitung zum Shop in Kürze...',
                'lyraRedirectErrorMessageDefault' => 'Weiterleitung zum Shop in Kürze...'
            ],
            'fr' => [
                'lyraRedirectSuccessMessageDefault' => 'Redirection vers la boutique dans quelques instants...',
                'lyraRedirectErrorMessageDefault' => 'Redirection vers la boutique dans quelques instants...'
            ],
            'es' => [
                'lyraRedirectSuccessMessageDefault' => 'Redirección a la tienda en unos momentos...',
                'lyraRedirectErrorMessageDefault' => 'Redirección a la tienda en unos momentos...'
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function install(InstallContext $context): void
    {
        if (empty($this->defaultValues)) {
            return;
        }

        $this->setDefaultValues();
    }

    /**
     * {@inheritdoc}
     */
    public function update(UpdateContext $context): void
    {
        if (empty($this->defaultValues)) {
            return;
        }

        $this->setDefaultValues();
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(UninstallContext $context): void
    {
        if (empty($this->defaultValues)) {
            return;
        }

        $this->setDefaultValues();
    }

    /**
     * {@inheritdoc}
     */
    public function activate(ActivateContext $context): void
    {
        // Nothing to do here.
    }

    /**
     * {@inheritdoc}
     */
    public function deactivate(DeactivateContext $context): void
    {
        // Nothing to do here.
    }

    private function setDefaultValues()
    {
        $domain = 'LyranetworkLyra.config.';

        foreach ($this->defaultValues as $key => $value) {
            $configKey = $domain . $key;
            $this->systemConfigService->set($configKey, $value);
        }
    }

    private function getBaseUrl(Context $context): ?string
    {
        /**
         * @var EntityRepository $domainRepository
         */
        $domainRepository = $this->container->get('sales_channel_domain.repository');

        $criteria = new Criteria();
        $criteria->addAssociation('salesChannel');
        $criteria->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $criteria->addFilter(new EqualsFilter('salesChannel.active', '1'));
        $criteria->addFilter(new ContainsFilter('url', 'https'));

        $domains = $domainRepository->search($criteria, $context);
        if ($domains->count() === 0) {
            $criteria = new Criteria();
            $criteria->addAssociation('salesChannel');
            $criteria->addFilter(new EqualsFilter('salesChannel.active', '1'));

            $domains = $domainRepository->search($criteria, $context);
        }

        return ($domains->count() > 0) ? $domains->first()->getUrl() : '';
    }

    private function getShopwareOrderPlacedFlowActive(Context $context): bool
    {
        $shopwareVersion = $this->container->getParameter('kernel.shopware_version');
        if (version_compare($shopwareVersion, '6.4.6.0', '>=')) {
            /**
             * @var EntityRepository $flowRepository
             */
            $flowRepository = $this->container->get('flow.repository');

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('name', 'Order placed'));
            $orderPlacedFlow = $flowRepository->search($criteria, $context);

            return ($orderPlacedFlow->count() > 0) ? $orderPlacedFlow->first()->isActive() : false;
        }

        return false;
    }
}
