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

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\Language\LanguageCollection;

class LocaleCodeService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    public function __construct(EntityRepositoryInterface $languageRepository)
    {
        $this->languageRepository = $languageRepository;
    }

    public function getLocaleCodeFromContext(Context $context): string
    {
        $languageId = $context->getLanguageId();
        $criteria = new Criteria([$languageId]);
        $criteria->addAssociation('locale');

        /**
         * @var LanguageCollection $languageCollection
         */
        $languageCollection = $this->languageRepository->search($criteria, $context)->getEntities();

        $language = $languageCollection->get($languageId);
        if ($language === null) {
            return 'en';
        }

        $locale = $language->getLocale();
        if (! $locale) {
            return 'en';
        }

        $langCode = explode('-', $locale->getCode());
        return strtolower($langCode[0]);
    }
}
