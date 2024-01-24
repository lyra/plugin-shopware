<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

namespace Lyranetwork\Lyra\Struct;

use Shopware\Core\Framework\Struct\Struct;

class CheckoutConfirmData extends Struct
{
    final public const EXTENSION_NAME = 'lyra';

    protected ?string $template = null;

    protected ?string $cardDataMode = null;

    protected ?bool $restPopinMode = null;

    protected ?string $restIdentifierToken = null;

    protected ?string $restTheme = null;

    protected ?bool $restCompactMode = null;

    protected ?string $paymentMeansGroupingThreshold = null;

    protected ?string $restJsClient = null;

    protected ?string $publicKey = null;

    protected ?string $language = null;

    protected ?String $paymentMethodId = null;

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function getCardDataMode(): ?string
    {
        return $this->cardDataMode;
    }

    public function getRestPopinMode(): ?bool
    {
        return $this->restPopinMode;
    }

    public function getRestIdentifierToken(): ?string
    {
        return $this->restIdentifierToken;
    }

    public function getRestTheme(): ?string
    {
        return $this->restTheme;
    }

    public function getRestCompactMode(): ?bool
    {
        return $this->restCompactMode;
    }

    public function getPaymentMeansGroupingThreshold(): ?string
    {
        return $this->paymentMeansGroupingThreshold;
    }

    public function getRestJsClient(): ?string
    {
        return $this->restJsClient;
    }

    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getPaymentMethodId(): ?string
    {
        return $this->paymentMethodId;
    }
}