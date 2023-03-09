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

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class FlowService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $flowRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(EntityRepositoryInterface $flowRepository, LoggerInterface $logger)
    {
        $this->flowRepository = $flowRepository;
        $this->logger = $logger;
    }

    public function getFlowIdByName(string $name): ?string
    {
        if (! empty($name)) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('name', $name));

            return $this->flowRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        }
    }

    public function updateFlowActive(string $name, bool $active, Context $context)
    {
        $flowId = $this->getFlowIdByName($name);
        if ($flowId) {
            $flow = [
                'id' => $flowId,
                'active' => $active
            ];

            $this->logger->info("Update flow:" . $name . ', set Active parameter to ' . (($active) ? 'true.' : 'false.'));
            $this->flowRepository->update([$flow], $context);
        }
    }
}