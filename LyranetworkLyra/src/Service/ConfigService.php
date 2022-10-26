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
namespace Lyranetwork\Lyra\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigService
{
    public const SYSTEM_CONFIG_DOMAIN = 'LyranetworkLyra.config.';

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * ConfigService constructor.
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @param string $setting
     * @return mixed|null
     */
    public function get(string $configSetting, ?string $salesChannelId = null)
    {
        $setting = 'lyra' .  str_replace(' ', '', ucwords(str_replace('_', ' ', $configSetting)));
        switch ($setting) {
            case 'lyraRedirectEnabled':
                $lyraRedirectEnabled = (bool) $this->systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN . $setting, $salesChannelId);
                return ($lyraRedirectEnabled) ? "true" : "false";

            case 'lyraAvailableLanguages':
            case 'lyraPaymentCards':
                $languages = (array) $this->systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN . $setting, $salesChannelId);
                return implode(';', $languages);

            default:
                return (string) $this->systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN . $setting, $salesChannelId);
        }
    }

    public function updateConfig(array $settings, ?string $salesChannelId = null): void
    {
        foreach ($settings as $key => $value) {
            $this->systemConfigService->set(
                self::SYSTEM_CONFIG_DOMAIN . $key,
                $value,
                $salesChannelId
            );
        }
    }
}
