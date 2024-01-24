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
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Lyranetwork\Lyra\PaymentMethods\Standard;
use Lyranetwork\Lyra\PaymentMethods\Rest;

class AccountOrderSubscriber implements EventSubscriberInterface
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
            AccountOrderPageLoadedEvent::class => 'onAccountOrder'
        ];
    }

    public function onAccountOrder(AccountOrderPageLoadedEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $isLyraPayment = ($salesChannelContext->getPaymentMethod()->getHandlerIdentifier() === Standard::class || $salesChannelContext->getPaymentMethod()->getHandlerIdentifier() === Rest::class);

        if (! $isLyraPayment) {
            return;
        }

        $session = $event->getRequest()->getSession();

        if ($session->has('lyraTechError') && $session->get('lyraTechError')) {
            $message = $this->translator->trans('lyraPaymentFatal');
            $session->getFlashBag()->add('warning', $message);
        }

        if ($session->has('lyraPaymentError') && $session->get('lyraPaymentError')) {
            $message = $this->translator->trans('lyraPaymentError');
            $session->getFlashBag()->add('warning', $message);
        }

        if ($session->has('lyraPaymentCancel') && $session->get('lyraPaymentCancel')) {
            $message = $this->translator->trans('lyraPaymentCancel');
            $session->getFlashBag()->add('warning', $message);
        }
    }
}