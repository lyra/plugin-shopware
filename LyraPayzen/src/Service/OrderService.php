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
namespace LyraPayment\Payzen\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\Checkout\Order\OrderStates;

class OrderService
{
    /**
     * @var EntityRepository
     */
    protected $orderRepository;

    /**
     *  @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(EntityRepository $orderRepository, StateMachineRegistry $stateMachineRegistry, LoggerInterface $logger)
    {
        $this->orderRepository = $orderRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->logger = $logger;
    }

    /**
     * Return the order repository.
     *
     * @return EntityRepository
     */
    public function getRepository()
    {
        return $this->orderRepository;
    }

    /**
     * Return an order entity, enriched with associations.
     *
     * @param string $orderId
     * @param Context $context
     * @return OrderEntity|null
     */
    public function getOrder(string $orderId, Context $context): ?OrderEntity
    {
        $order = null;

        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $orderId));
            $criteria->addAssociation('currency');
            $criteria->addAssociation('addresses');
            $criteria->addAssociation('language');
            $criteria->addAssociation('language.locale');
            $criteria->addAssociation('lineItems');
            $criteria->addAssociation('deliveries');
            $criteria->addAssociation('deliveries.shippingOrderAddress');

            /**
             * @var OrderEntity $order
             */
            $order = $this->orderRepository->search($criteria, $context)->first();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }

        return $order;
    }

    /**
     * Return an order entity, enriched with associations.
     *
     * @param string $orderId
     * @param Context $context
     * @return OrderEntity|null
     */
    public function filterOrderNumber(string $orderNumber, Context $context): ?OrderEntity
    {
        $order = null;

        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
            $criteria->addAssociation('currency');
            $criteria->addAssociation('addresses');
            $criteria->addAssociation('language');
            $criteria->addAssociation('language.locale');
            $criteria->addAssociation('transactions');
            $criteria->addAssociation('lineItems');
            $criteria->addAssociation('deliveries');
            $criteria->addAssociation('deliveries.shippingOrderAddress');

            /**
             * @var OrderEntity $order
             */
            $order = $this->orderRepository->search($criteria, $context)->first();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }

        return $order;
    }

    public function saveOrderStatus(Context $context, string $orderId, string $transitionName)
    {
        try {
            /**
             * @var OrderEntity $order
             */
            $order = $this->getOrder($orderId, $context);
            /**
             * @var string $order
             */
            $orderStatus = $order->getStateMachineState()->getTechnicalName();
            if (($orderStatus === OrderStates::STATE_CANCELLED) && ($transitionName === StateMachineTransitionActions::ACTION_PROCESS)) {
                $this->stateMachineRegistry->transition(new Transition(OrderDefinition::ENTITY_NAME, $orderId, StateMachineTransitionActions::ACTION_REOPEN, 'stateId'), $context);
                $this->logger->info('Order #' . $order->getOrderNumber() . ' status updated from ' . $orderStatus . ' to ' . OrderStates::STATE_OPEN . '.');
            }

            $this->stateMachineRegistry->transition(new Transition(OrderDefinition::ENTITY_NAME, $orderId, $transitionName, 'stateId'), $context);
            $this->logger->info('Order #' . $order->getOrderNumber() . ' status transition: ' . $transitionName . '.');
        } catch (IllegalTransitionException $exception) {
            // Illegal transition handling (Cancel -> Cancel). Do nothing.
        }
    }
}
