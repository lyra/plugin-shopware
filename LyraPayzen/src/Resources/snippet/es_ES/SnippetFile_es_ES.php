<?php
/**
 * Copyright © Lyra Network.
 * This file is part ofuse Shopware\Core\Framework\Snippet\Files\SnippetFileInterface;
.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);
namespace LyraPayment\Payzen\Resources\snippet\es_ES;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_es_ES implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'payzen.es-ES';
    }

    public function getPath(): string
    {
        return __DIR__ . '/payzen.es-ES.json';
    }

    public function getIso(): string
    {
        return 'es-ES';
    }

    public function getAuthor(): string
    {
        return 'Lyra Network';
    }

    public function isBase(): bool
    {
        return false;
    }
}
