/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

const { Application } = Shopware;
const ApiService = Shopware.Classes.ApiService;

class LyranetworkLyraSettingsService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'lyra') {
        super(httpClient, loginService, apiEndpoint);
    }

    getFeatures() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/get-features`,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getCardTypes() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/get-card-types`,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getLanguages() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/get-languages`,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getDocFiles() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/get-doc-files`,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getPaymentStatuses() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(
                `_action/${this.getApiBasePath()}/get-payment-statuses`,
                {
                    headers: headers
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

Application.addServiceProvider('LyranetworkLyraSettingsService', (container) => {
    const initContainer = Application.getContainer('init');

    return new LyranetworkLyraSettingsService(initContainer.httpClient, container.loginService);
});