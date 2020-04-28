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
namespace LyraPayment\Payzen\Subscriber;

use LyraPayment\Payzen\Payzen\PayzenTools;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


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
        $isPayzenPayment = ($salesChannelContext->getPaymentMethod()->getName() === PayzenTools::getDefault('GATEWAY_NAME'));

        if (! $isPayzenPayment) {
            return;
        }

        $session = $event->getRequest()->getSession();

        if ($session->has('payzenGoingIntoProductionInfo') && $session->get('payzenGoingIntoProductionInfo')) {
            $message = $this->translator->trans('payzenGoingIntoProductionInfo');
            $session->getFlashBag()->add('warning', $message);
        }

        if ($session->has('payzenCheckUrlWarn') && $session->get('payzenCheckUrlWarn')) {
            $message = $this->translator->trans('payzenCheckUrlWarn');
            $session->getFlashBag()->add('warning', $message);

            $message = $this->translator->trans('payzenCheckUrlWarnDetails');
            $session->getFlashBag()->add('warning', $message);
        }

        if ($session->has('payzenTechError') && $session->get('payzenTechError')) {
            $message = $this->translator->trans('payzenPaymentFatal');
            $session->getFlashBag()->add('warning', $message);
        }
    }
}
