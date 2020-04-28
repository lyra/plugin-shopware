/**
 * Copyright © Lyra Network.
 * This file is part of PayZen for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

const { Module } = Shopware;
import './page/lyrapayment-payzen-settings';

import './extension/sw-order';
import './extension/sw-settings-index';
import './extension/sw-plugin';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';
import esES from './snippet/es-ES.json';
import frFR from './snippet/fr-FR.json';

Module.register('lyrapayment-payzen', {
    type: 'plugin',
    name: 'Payzen',
    title: 'lyrapayment-payzen.payzenTitle',
    description: 'lyrapayment-payzen.payzenGeneral.descriptionTextModule',
    version: '2.0.0',
    icon: 'default-action-settings',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
        'es-ES': esES,
        'fr-FR': frFR
    },

    routes: {
        index: {
            component: 'lyrapayment-payzen-settings',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    }
});