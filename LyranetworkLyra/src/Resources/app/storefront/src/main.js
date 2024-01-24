/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

import LyraRestPlugin from "./lyranetwork-lyra/lyra-payment.rest";

const PluginManager = window.PluginManager;

PluginManager.register('LyraRest', LyraRestPlugin, '[data-lyra-rest]')

if (module.hot) {
    module.hot.accept();
}