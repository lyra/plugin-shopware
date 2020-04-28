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
namespace LyraPayment\Payzen\Installer;

use LyraPayment\Payzen\Payzen\PayzenTools;
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

class PayzenConfigInstaller
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

    public function __construct(ContainerInterface $container, String $path)
    {
        $this->container = $container;
        $this->systemConfigService = $this->container->get(SystemConfigService::class);
        $this->initTranslations();
        $this->initDefaults();
    }

    private function initDefaults()
    {
        $lang = PayzenTools::getDefault('LANGUAGE');

        $this->defaultValues = [
            'payzenDevelopedBy' => 'Lyra Network (https://www.lyra.com/)',
            'payzenContactEmail' => PayzenTools::getDefault('SUPPORT_EMAIL'),
            'payzenModuleVersion' => PayzenTools::getDefault('PLUGIN_VERSION'),
            'payzenGatewayVersion' => PayzenTools::getDefault('GATEWAY_VERSION'),
            'payzenSiteId' => PayzenTools::getDefault('SITE_ID'),
            'payzenKeyTest' => PayzenTools::getDefault('KEY_TEST'),
            'payzenKeyProd' => PayzenTools::getDefault('KEY_PROD'),
            'payzenCtxMode' => PayzenTools::getDefault('CTX_MODE'),
            'payzenSignAlgo' => PayzenTools::getDefault('SIGN_ALGO'),
            'payzenCheckUrl' => $this->getBaseUrl() . '/PaymentPayzen/finalize',
            'payzenPlatformUrl' => PayzenTools::getDefault('GATEWAY_URL'),
            'payzenLanguage' => PayzenTools::getDefault('LANGUAGE'),
            'payzenRedirectSuccessTimeout' => '5',
            'payzenValidationMode' => '',
            'payzenRedirectSuccessMessage' => $this->translations[$lang]['payzenRedirectSuccessMessageDefault'],
            'payzenRedirectErrorTimeout' => '5',
            'payzenRedirectErrorMessage' => $this->translations[$lang]['payzenRedirectErrorMessageDefault'],
            'payzenReturnMode' => 'GET',
            'payzenPaymentStatusOnSuccess' => 'paid'
        ];
    }

    private function initTranslations()
    {
        $this->translations = [
            'en' => [
                'payzenRedirectSuccessMessageDefault' => 'Redirection to shop in a few seconds...',
                'payzenRedirectErrorMessageDefault' => 'Redirection to shop in a few seconds...'
            ],
            'de' => [
                'payzenRedirectSuccessMessageDefault' => 'Weiterleitung zum Shop in Kürze...',
                'payzenRedirectErrorMessageDefault' => 'Weiterleitung zum Shop in Kürze...'
            ],
            'fr' => [
                'payzenRedirectSuccessMessageDefault' => 'Redirection vers la boutique dans quelques instants...',
                'payzenRedirectErrorMessageDefault' => 'Redirection vers la boutique dans quelques instants...'
            ],
            'es' => [
                'payzenRedirectSuccessMessageDefault' => 'Redirección a la tienda en unos momentos...',
                'payzenRedirectErrorMessageDefault' => 'Redirección a la tienda en unos momentos...'
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
        $domain = 'LyraPaymentPayzen.config.';

        foreach ($this->defaultValues as $key => $value) {
            $configKey = $domain . $key;
            $this->systemConfigService->set($configKey, $value);
        }
    }

    private function getBaseUrl(): ?string
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

        $domains = $domainRepository->search($criteria, Context::createDefaultContext());
        if ($domains->count() === 0) {
            $criteria = new Criteria();
            $criteria->addAssociation('salesChannel');
            $criteria->addFilter(new EqualsFilter('salesChannel.active', '1'));

            $domains = $domainRepository->search($criteria, Context::createDefaultContext());
        }

        return ($domains->count() > 0) ? $domains->first()->getUrl() : '';
    }
}
