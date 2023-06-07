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
namespace Lyranetwork\Lyra\Controller;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\StateMachineCollection;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use Lyranetwork\Lyra\Sdk\Tools;
use Lyranetwork\Lyra\Sdk\Form\Api as LyraApi;
use Lyranetwork\Lyra\Service\ConfigService;
use Lyranetwork\Lyra\Service\FlowService;

class SettingsController extends AbstractController
{
    /**
     * @var EntityRepositoryInterface
     */
    private $stateMachineStateRepository;

    /**
     * @var FlowService
     */
    private $flowService;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var string
     */
    private $shopwareDirectory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        EntityRepositoryInterface $stateMachineStateRepository,
        FlowService $flowService,
        ConfigService $configService,
        string $shopwareDirectory,
        LoggerInterface $logger
    ) {
        $this->stateMachineStateRepository = $stateMachineStateRepository;
        $this->flowService = $flowService;
        $this->configService = $configService;
        $this->shopwareDirectory = $shopwareDirectory;
        $this->logger = $logger;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/lyra/get-features", name="api.action.lyra.get.features", methods={"GET"})
     * @Route("/api/v{version}/_action/lyra/get-features", name="api.action.lyra.get.features.legacy", methods={"GET"})
     */
    public function getFeatures(Request $request, Context $context): JsonResponse
    {
        return new JsonResponse(
            [
                'qualif' => Tools::$pluginFeatures['qualif'],
                'shatwo' => Tools::$pluginFeatures['shatwo']
            ]
        );
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/lyra/get-card-types", name="api.action.lyra.get.card_types", methods={"GET"})
     * @Route("/api/v{version}/_action/lyra/get-card-types", name="api.action.lyra.get.card_types.legacy", methods={"GET"})
     */
    public function getCardTypes(Request $request, Context $context): JsonResponse
    {
        $cardTypes = LyraApi::getSupportedCardTypes();
        return new JsonResponse(['data' => $cardTypes, 'total' => count($cardTypes)]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/lyra/get-languages", name="api.action.lyra.get.languages", methods={"GET"})
     * @Route("/api/v{version}/_action/lyra/get-languages", name="api.action.lyra.get.languages.legacy", methods={"GET"})
     */
    public function getLanguages(Request $request, Context $context): JsonResponse
    {
        $supportedLanguages = LyraApi::getSupportedLanguages();
        return new JsonResponse(['data' => $supportedLanguages, 'total' => count($supportedLanguages)]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/lyra/get-doc-files", name="api.action.lyra.get.doc_files", methods={"GET"})
     * @Route("/api/v{version}/_action/lyra/get-doc-files", name="api.action.lyra.get.doc_files.legacy", methods={"GET"})
     */
    public function getDocFiles(Request $request, Context $context): JsonResponse
    {
        $docPattern= $this->shopwareDirectory . '/public/bundles/lyranetworklyra/installation_doc/' . Tools::getDocPattern();

        // Get documentation links.
        $docs = [];
        $filenames = glob(str_replace('\\', '/', $docPattern));

        $languages = [
            'fr' => 'Français',
            'en' => 'English',
            'es' => 'Español',
            'de' => 'Deutsch'
            // Complete when other languages are managed.
        ];

        foreach (LyraApi::getOnlineDocUri() as $lang => $docUri) {
            $docs[] = [
                'name' => 'lyraDocumentation' . $lang,
                'title' => $languages[strtolower($lang)],
                'link' => $docUri . 'shopware64/sitemap.html'
            ];
        }

        return new JsonResponse(['data' => $docs, 'total' => count($docs)]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/lyra/get-payment-statuses", name="api.action.lyra.get.payment_statuses", methods={"GET"})
     * @Route("/api/v{version}/_action/lyra/get-payment-statuses", name="api.action.lyra.get.payment_statuses.legacy", methods={"GET"})
     */
    public function getPaymentStatuses(Request $request, Context $context): JsonResponse
    {
        $criteria = new Criteria();
        $criteria->addAssociation('stateMachine');
        $criteria->addFilter(new EqualsFilter('stateMachine.technicalName', 'order_transaction.state'));

        $entities = $this->stateMachineStateRepository->search($criteria, $context)->getEntities();

        $paymentStatuses = [];
        if($entities instanceof StateMachineStateCollection) {
            $elements = $entities->getElements();
            foreach ($elements as $value) {
                $paymentStatuses[] = [
                    'label' => $value->getName(),
                    'value' => $value->getTechnicalName()
                ];
            }
        }

        return new JsonResponse(['data' => $paymentStatuses, 'total' => count($paymentStatuses)]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/lyra/is-flow", name="api.action.lyra.is.flow", methods={"GET"})
     * @Route("/api/v{version}/_action/lyra/is-flow", name="api.action.lyra.is.flow.legacy", methods={"GET"})
     */
    public function isFlow(Request $request, Context $context): JsonResponse
    {
        $shopwareVersion = $request->query->has('shopwareVersion') ? (string) $request->query->get('shopwareVersion') : null;
        return new JsonResponse(
            [
                'isFlow' => (! empty($shopwareVersion) && version_compare($shopwareVersion, '6.4.6.0', '>='))
            ]
        );
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/lyra/set-order-placed-flow", name="api.action.lyra.set.order_placed_flow", methods={"POST"})
     * @Route("/api/v{version}/_action/lyra/set-order-placed-flow", name="api.action.lyra.set.order_placed_flow.legacy", methods={"POST"})
     */
    public function setOrderPlacedFlow(Request $request, Context $context): JsonResponse
    {
        $shopwareVersion = $request->request->has('shopwareVersion') ? (string) $request->request->get('shopwareVersion') : null;
        if (! empty($shopwareVersion) && version_compare($shopwareVersion, '6.4.6.0', '>=')) {
            $salesChannelId = $request->request->has('salesChannelId') ? (string) $request->request->get('salesChannelId') : null;
            $active = ($this->configService->get('order_placed_flow_enabled', $salesChannelId) == 'true') ? true : false;
            $this->flowService->updateFlowActive('Order placed', $active, $context);
        }

        return $this->json(['success' => true,]);
    }
}
