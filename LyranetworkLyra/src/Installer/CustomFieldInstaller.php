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
namespace Lyranetwork\Lyra\Installer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CustomFieldInstaller
{
    public const TRANSACTION_ID = 'lyra_transaction_id';
    public const TRANSACTION_UUID = 'lyra_transaction_uuid';
    public const TRANSACTION_TYPE = 'lyra_transaction_type';
    public const TRANSACTION_MESSAGE = 'lyra_transaction_message';
    public const TRANSACTION_STATUS = 'lyra_transaction_status';
    public const MEANS_OF_PAYMENT = 'lyra_means_of_payment';
    public const CARD_NUMBER = 'lyra_card_number';
    public const CARD_EXPIRATION_DATE = 'lyra_card_expiration_date';
    public const ORDER_ID = 'lyra_order_id';

    public const FIELDSET_ID_ORDER_TRANSACTION = '25193afbc92646a8b898b211518ccf6d';

    /**
     * @var EntityRepository
     */
    private $customFieldRepository;

    /**
     * @var EntityRepository
     */
    private $customFieldSetRepository;

    /**
     * @var array
     */
    private $customFields;

    /**
     * @var array
     */
    private $customFieldSets;

    public function __construct(ContainerInterface $container)
    {
        $this->customFieldSetRepository = $container->get('custom_field_set.repository');
        $this->customFieldRepository = $container->get('custom_field.repository');

        $this->customFieldSets = [
            [
                'id' => self::FIELDSET_ID_ORDER_TRANSACTION,
                'name' => 'order_transaction_lyra_payment',
                'config' => [
                    'label' => [
                        'en-GB' => 'Lyra Collect',
                        'de-DE' => 'Lyra Collect',
                        'fr-FR' => 'Lyra Collect',
                        'es-ES' => 'Lyra Collect'
                    ]
                ],
                'relation' => [
                    'id' => '25193afbc92646a8b898b211518ccf6d',
                    'entityName' => 'order_transaction'
                ]
            ]
        ];

        $this->customFields = [
            [
                'id' => 'fe5f4e10cd1a4f6e9710207638c0c9eb',
                'name' => self::TRANSACTION_ID,
                'type' => CustomFieldTypes::TEXT,
                'customFieldSetId' => self::FIELDSET_ID_ORDER_TRANSACTION
            ],
            [
                'id' => '9bafb69059bf467bb3445c445d395c7e',
                'name' => self::TRANSACTION_UUID,
                'type' => CustomFieldTypes::TEXT,
                'customFieldSetId' => self::FIELDSET_ID_ORDER_TRANSACTION
            ],
            [
                'id' => '402f0807d3eb44ccadb9a05737ca1ecd',
                'name' => self::TRANSACTION_TYPE,
                'type' => CustomFieldTypes::TEXT,
                'customFieldSetId' => self::FIELDSET_ID_ORDER_TRANSACTION
            ],
            [
                'id' => '018d17f181637ae9be49a335d977c23c',
                'name' => self::TRANSACTION_STATUS,
                'type' => CustomFieldTypes::TEXT,
                'customFieldSetId' => self::FIELDSET_ID_ORDER_TRANSACTION
            ],
            [
                'id' => '86235308bf4c4bf5b4db7feb07d2a63d',
                'name' => self::TRANSACTION_MESSAGE,
                'type' => CustomFieldTypes::TEXT,
                'customFieldSetId' => self::FIELDSET_ID_ORDER_TRANSACTION
            ],
            [
                'id' => '81f06a4b755e49faaeb42cf0db62c36d',
                'name' => self::MEANS_OF_PAYMENT,
                'type' => CustomFieldTypes::TEXT,
                'customFieldSetId' => self::FIELDSET_ID_ORDER_TRANSACTION
            ],
            [
                'id' => '944b5716791c417ebdf7cc333ad5264f',
                'name' => self::CARD_NUMBER,
                'type' => CustomFieldTypes::TEXT,
                'customFieldSetId' => self::FIELDSET_ID_ORDER_TRANSACTION
            ],
            [
                'id' => 'bee7f0790bc14763b727d623dd646086',
                'name' => self::CARD_EXPIRATION_DATE,
                'type' => CustomFieldTypes::TEXT,
                'customFieldSetId' => self::FIELDSET_ID_ORDER_TRANSACTION
            ],
            [
                'id' => '018c68a027b17e85859751dd8da27161',
                'name' => self::ORDER_ID,
                'type' => CustomFieldTypes::TEXT,
                'customFieldSetId' => self::FIELDSET_ID_ORDER_TRANSACTION
            ]
        ];
    }

    public function install(InstallContext $context): void
    {
        foreach ($this->customFieldSets as $customFieldSet) {
            $this->upsertCustomFieldSet($customFieldSet, $context->getContext());
        }

        foreach ($this->customFields as $customField) {
            $this->upsertCustomField($customField, $context->getContext());
        }
    }

    public function update(UpdateContext $context): void
    {
        foreach ($this->customFieldSets as $customFieldSet) {
            $this->upsertCustomFieldSet($customFieldSet, $context->getContext());
        }

        foreach ($this->customFields as $customField) {
            $this->upsertCustomField($customField, $context->getContext());
        }
    }

    public function uninstall(UninstallContext $context): void
    {
        foreach ($this->customFieldSets as $customFieldSet) {
            $this->deactivateCustomFieldSet($customFieldSet, $context->getContext());
        }

        foreach ($this->customFields as $customField) {
            $this->deactivateCustomField($customField, $context->getContext());
        }
    }

    public function activate(ActivateContext $context): void
    {
        foreach ($this->customFieldSets as $customFieldSet) {
            $this->upsertCustomFieldSet($customFieldSet, $context->getContext());
        }

        foreach ($this->customFields as $customField) {
            $this->upsertCustomField($customField, $context->getContext());
        }
    }

    public function deactivate(DeactivateContext $context): void
    {
        foreach ($this->customFieldSets as $customFieldSet) {
            $this->deactivateCustomFieldSet($customFieldSet, $context->getContext());
        }

        foreach ($this->customFields as $customField) {
            $this->deactivateCustomField($customField, $context->getContext());
        }
    }

    private function upsertCustomField(array $customField, Context $context): void
    {
        $data = [
            'id' => $customField['id'],
            'name' => $customField['name'],
            'type' => $customField['type'],
            'active' => true,
            'customFieldSetId' => $customField['customFieldSetId']
        ];

        $this->customFieldRepository->upsert([$data], $context);
    }

    private function deactivateCustomField(array $customField, Context $context): void
    {
        $data = [
            'id' => $customField['id'],
            'name' => $customField['name'],
            'type' => $customField['type'],
            'active' => false,
            'customFieldSetId' => $customField['customFieldSetId']
        ];

        $this->customFieldRepository->upsert([$data], $context);
    }

    private function upsertCustomFieldSet(array $customFieldSet, Context $context): void
    {
        $data = [
            'id' => $customFieldSet['id'],
            'name' => $customFieldSet['name'],
            'config' => $customFieldSet['config'],
            'active' => true,
            'relations' => [
                [
                    'id' => $customFieldSet['relation']['id'],
                    'entityName' => $customFieldSet['relation']['entityName']
                ]
            ]
        ];

        $this->customFieldSetRepository->upsert([$data], $context);
    }

    private function deactivateCustomFieldSet(array $customFieldSet, Context $context): void
    {
        $data = [
            'id' => $customFieldSet['id'],
            'name' => $customFieldSet['name'],
            'config' => $customFieldSet['config'],
            'active' => false,
            'relations' => [
                [
                    'id' => $customFieldSet['relation']['id'],
                    'entityName' => $customFieldSet['relation']['entityName']
                ]
            ]
        ];

        $this->customFieldSetRepository->upsert([$data], $context);
    }
}