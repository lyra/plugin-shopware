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

use Lyranetwork\Lyra\PaymentMethods\Rest;
use Lyranetwork\Lyra\PaymentMethods\Standard;
use Lyranetwork\Lyra\Sdk\RestData;
use Lyranetwork\Lyra\Service\LocaleCodeService;
use Psr\Log\LoggerInterface;
use Lyranetwork\Lyra\Struct\CheckoutConfirmData;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\Routing\RouterInterface;

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

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var LocaleCodeService
     */
    private $localeCodeService;

    /**
     * @var RestData
     */
    private $restData;

    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(
        ConfigService $configService,
        PaymentMethodService $paymentMethodService,
        RestData $restData,
        LoggerInterface $logger,
        LocaleCodeService $localeCodeService,
        RouterInterface $router
    ) {
        $this->configService = $configService;
        $this->paymentMethodService = $paymentMethodService;
        $this->restData = $restData;
        $this->logger = $logger;
        $this->localeCodeService = $localeCodeService;
        $this->router = $router;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => ['onConfirmPageLoaded', 1]
        ];
    }

    public function onConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $page = $event->getPage();

        $currency = $event->getSalesChannelContext()->getSalesChannel()->getCurrency()->getIsoCode(); // Current shop currency.
        $amount = $event->getPage()->getCart()->getPrice()->getTotalPrice(); // Current basket amount.
        $min = $this->configService->get('min_amount'); // Minimum amount to activate this module.
        $max = $this->configService->get('max_amount'); // Maximum amount to activate this module.
        $available = true;

        if (($min && $amount < $min) || ($max && $amount > $max) || ! LyraApi::findCurrencyByAlphaCode($currency)) {
            $this->removePaymentMethodFromConfirmPage($event, Standard::class);
            $this->removePaymentMethodFromConfirmPage($event, Rest::class);
            $available = false;
        }

        $cardDataMode = $this->configService->get('card_data_mode');

        if ($this->restData->isSmartform() && $available) {
            $template = '@Storefront/storefront/page/checkout/confirm/template-rest.html.twig';
            $popinMode = $this->configService->get('rest_popin_mode') == '1';
            $theme = $this->configService->get('rest_theme');
            $compactMode = $this->configService->get('rest_compact_mode') == '1';
            $threshold = $this->configService->get('rest_threshold');
            $jsClient = $this->configService->get('js_client_url');
            $pubKey = $this->restData->getPublicKey();
            $language = $this->localeCodeService->getLocaleCodeFromContext( $event->getSalesChannelContext()->getContext());;
            $paymentMethodId = $this->paymentMethodService->getPaymentMethodId($event->getContext(), Rest::class);

            $token = $this->restData->getToken($event);

            if ($page->hasExtension(CheckoutConfirmData::EXTENSION_NAME)) {
                $lyraData = $page->getExtension(CheckoutConfirmData::EXTENSION_NAME);
            } else {
                $lyraData = new CheckoutConfirmData();
            }

            if ($lyraData !== null && $token) {
                $lyraData->assign([
                    'template' => $template,
                    'cardDataMode' => $cardDataMode,
                    'restPopinMode' => $popinMode,
                    'restIdentifierToken' => $token,
                    'restTheme' => $theme,
                    'restCompactMode' => $compactMode,
                    'paymentMeansGroupingThreshold' => $threshold,
                    "restJsClient" => $jsClient,
                    'pubKey' => $pubKey,
                    'language' => $language,
                    'paymentMethodId' => $paymentMethodId
                ]);

                $page->addExtension(CheckoutConfirmData::EXTENSION_NAME, $lyraData);
                $this->removePaymentMethodFromConfirmPage($event, Standard::class);
            } else {
                $this->removePaymentMethodFromConfirmPage($event, Rest::class);
            }
        } else {
            $this->removePaymentMethodFromConfirmPage($event, Rest::class);
        }
    }

    private function removePaymentMethodFromConfirmPage(CheckoutConfirmPageLoadedEvent $event, $paymentMethodClass): void
    {
        $paymentMethodCollection = $event->getPage()->getPaymentMethods();

        $paymentMethodCollection->remove($this->paymentMethodService->getPaymentMethodId($event->getContext(), $paymentMethodClass));
    }
}
