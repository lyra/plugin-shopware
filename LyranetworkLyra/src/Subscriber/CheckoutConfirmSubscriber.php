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
namespace Lyranetwork\Lyra\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

use Lyranetwork\Lyra\Service\ConfigService;
use Lyranetwork\Lyra\Service\PaymentMethodService;
use Lyranetwork\Lyra\Sdk\Form\Api as LyraApi;

class CheckoutConfirmSubscriber implements EventSubscriberInterface
{
    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var PaymentMethodService
     */
    private $paymentMethodService;

    public function __construct(ConfigService $configService, PaymentMethodService $paymentMethodService)
    {
        $this->configService = $configService;
        $this->paymentMethodService = $paymentMethodService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => ['onConfirmPageLoaded', 1]
        ];
    }

    public function onConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $currency = $event->getSalesChannelContext()->getSalesChannel()->getCurrency()->getIsoCode(); // Current shop currency.
        $amount = $event->getPage()->getCart()->getPrice()->getTotalPrice(); // Current basket amount.
        $min = $this->configService->get('min_amount'); // Minimum amount to activate this module.
        $max = $this->configService->get('max_amount'); // Maximum amount to activate this module.

        if (($min && $amount < $min) || ($max && $amount > $max) || ! LyraApi::findCurrencyByAlphaCode($currency)) {
            $this->removePaymentMethodFromConfirmPage($event);
        }
    }

    private function removePaymentMethodFromConfirmPage(CheckoutConfirmPageLoadedEvent $event): void
    {
        $paymentMethodCollection = $event->getPage()->getPaymentMethods();

        $paymentMethodCollection->remove($this->paymentMethodService->getPaymentMethodId($event->getContext()));
    }
}
