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

use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Lyranetwork\Lyra\PaymentMethods\Standard;


class CheckoutFinishSubscriber implements EventSubscriberInterface
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutFinishPageLoadedEvent::class => 'onCheckoutFinish'
        ];
    }

    public function onCheckoutFinish(CheckoutFinishPageLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $isLyraPayment = ($salesChannelContext->getPaymentMethod()->getHandlerIdentifier() === Standard::class);

        if (! $isLyraPayment) {
            return;
        }

        $session = $event->getRequest()->getSession();

        if ($session->has('lyraGoingIntoProductionInfo') && $session->get('lyraGoingIntoProductionInfo')) {
            $message = $this->translator->trans('lyraGoingIntoProductionInfo');
            $session->getFlashBag()->add('warning', $message);
        }

        if ($session->has('lyraCheckUrlWarn') && $session->get('lyraCheckUrlWarn')) {
            $message = $this->translator->trans('lyraCheckUrlWarn');
            $session->getFlashBag()->add('warning', $message);

            $message = $this->translator->trans('lyraCheckUrlWarnDetails');
            $session->getFlashBag()->add('warning', $message);
        }

        if ($session->has('lyraTechError') && $session->get('lyraTechError')) {
            $message = $this->translator->trans('lyraPaymentFatal');
            $session->getFlashBag()->add('warning', $message);
        }
    }
}
