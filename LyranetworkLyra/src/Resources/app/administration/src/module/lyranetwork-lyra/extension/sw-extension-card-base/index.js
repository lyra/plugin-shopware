/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

const { Component } = Shopware;

Component.override('sw-extension-card-base', {
    created() {
        if (this.extension.name === "LyranetworkLyra") {
            this.extension.configurable = false;
        }
    }
});