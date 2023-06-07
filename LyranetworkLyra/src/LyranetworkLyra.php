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
namespace Lyranetwork\Lyra;

use Lyranetwork\Lyra\Installer\ConfigInstaller;
use Lyranetwork\Lyra\Installer\CustomFieldInstaller;
use Lyranetwork\Lyra\Installer\PaymentMethodInstaller;

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

class LyranetworkLyra extends Plugin
{
    public function install(InstallContext $context): void
    {
        (new ConfigInstaller($this->container, $context->getContext()))->install($context);

        (new PaymentMethodInstaller($this->container))->install($context);
        (new CustomFieldInstaller($this->container))->install($context);
    }

    public function uninstall(UninstallContext $context): void
    {
        // Only set the payment method as inactive when uninstalling. Removing the payment method would
        // cause data consistency issues, since the payment method might have been used in several orders.
        (new PaymentMethodInstaller($this->container))->uninstall($context);
    }

    public function activate(ActivateContext $context): void
    {
        (new PaymentMethodInstaller($this->container))->activate($context);
        (new CustomFieldInstaller($this->container))->activate($context);

        parent::activate($context);
    }

    public function deactivate(DeactivateContext $context): void
    {
        (new PaymentMethodInstaller($this->container))->deactivate($context);
        (new CustomFieldInstaller($this->container))->deactivate($context);

        parent::deactivate($context);
    }
}
