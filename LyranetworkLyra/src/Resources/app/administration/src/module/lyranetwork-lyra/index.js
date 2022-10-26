/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

const { Module } = Shopware;
import './component/lyranetwork-lyra-icon';

import './page/lyranetwork-lyra-settings';

import './extension/sw-order-detail-base';
import './extension/sw-settings-index';
import './extension/sw-extension-card-base';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';
import esES from './snippet/es-ES.json';
import frFR from './snippet/fr-FR.json';

let configuration = {
    type: 'plugin',
    name: 'Lyra Collect',
    title: 'lyraTitle',
    description: 'lyraGeneral.descriptionTextModule',
    version: '3.0.0',
    icon: 'default-action-settings',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
        'es-ES': esES,
        'fr-FR': frFR
    },

    routeMiddleware(next, currentRoute) {
        next(currentRoute);
    },

    routes: {
        index: {
            component: 'lyranetwork-lyra-settings',
            path: 'index',
            meta: {
                parentPath: 'sw.settings.index'
            }
        }
    },

    settingsItem: {
        name:   'lyranetwork-lyra',
        to:     'lyranetwork.lyra.index',
        label:  'lyraTitle',
        group:  'plugins',
        iconComponent: 'lyranetwork-lyra-icon',
        backgroundEnabled: false
    }
};

Module.register('lyranetwork-lyra', configuration);