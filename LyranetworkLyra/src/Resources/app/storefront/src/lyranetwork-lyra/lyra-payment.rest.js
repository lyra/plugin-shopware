/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

import Plugin from 'src/plugin-system/plugin.class';

export default class LyraRestPlugin extends Plugin {
    static options = {
        compactMode: false,
        groupThreshold: '',
        popinMode: false,
        language: 'fr-FR',
        paymentMethodId: ''
    };

    init() {
        window.addEventListener('load', this._configureSmartform.bind(this));

        const form = document.getElementById('confirmOrderForm');
        if (form) {
            form.addEventListener('submit', this._orderSubmit.bind(this));
        }
    }

    _configureSmartform() {
        const methodSelected = document.getElementById('paymentMethod' + this.options.paymentMethodId).checked;

        if (methodSelected) {
            if (this.options.compactMode) {
                KR.setFormConfig({cardForm: {layout: "compact"}, smartForm: {layout: "compact"}});
            }

            if (this.options.groupThreshold && !isNaN(this.options.groupThreshold)) {
                KR.setFormConfig({smartForm: {groupingThreshold: this.options.groupThreshold}});
            }

            KR.setFormConfig({
                language: this.options.language,
                form: {smartform: {singlePaymentButton: {visibility: false}}}
            });

            KR.onError(function(e) {
                document.getElementById('confirmFormSubmit').disabled = false;
            }.bind(this));

            KR.onPopinClosed(function() {
                document.getElementById('confirmFormSubmit').disabled = false;
            })

            const form = document.getElementById('confirmOrderForm');
            KR.onSubmit(function (res) {
                document.getElementById('lyraResponse').value = JSON.stringify(res);
                form.submit();
            });

            KR.onLoaded(function() {
                if (this.options.popinMode) {
                    let element = document.getElementsByClassName("kr-smart-button");
                    if (element.length > 0) {
                        element[0].style.display = 'none'
                    } else {
                        element = document.getElementsByClassName("kr-smart-form-modal-button");
                        if (element.length > 0) {
                            element[0].style.display = 'none'
                        }
                    }
                }
            }.bind(this))
        } else {
            document.getElementById('lyraPaymentMethodForm').style.display = 'none'
        }
    }

    _orderSubmit(event) {
        const methodSelected = document.getElementById('paymentMethod' + this.options.paymentMethodId).checked;
        if (methodSelected) {
            event.preventDefault();
            let isSmartform = document.getElementsByClassName('kr-smart-form');
            let smartformModalButton = document.getElementsByClassName('kr-smart-form-modal-button');

            // If popin mode.
            if (this.options.popinMode) {
                if (smartformModalButton.length === 0 && isSmartform.length > 0) {
                    let element = document.getElementsByClassName('kr-smart-button');
                    let paymentMethod = element.attr('kr-payment-method');
                    KR.openPaymentMethod(paymentMethod);
                } else {
                    KR.openPopin();
                }
            } else {
                KR.openSelectedPaymentMethod();
            }
        }
    }
}