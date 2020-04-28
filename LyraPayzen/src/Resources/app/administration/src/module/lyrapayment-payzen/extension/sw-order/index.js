/**
 * Copyright © Lyra Network.
 * This file is part of PayZen for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

const { Component, Mixin } = Shopware;
import template from './sw-order.html.twig';
import './sw-order.scss';

Component.override('sw-order-detail-base', {
    template,

    methods: {
        isPayzenPayment(transaction) {
            if (! transaction.customFields) {
                return false;
            }

            return transaction.customFields.payzen_transaction_id;
        },

        hasPayzenPayment(order) {
            let me = this;
            let isPayzen = false;

            if (! order.transactions) {
                return false;
            }

            order.transactions.map(function(transaction) {
                if (me.isPayzenPayment(transaction)) {
                    isPayzen = true;
                }
            });

            return isPayzen;
        }
    }
});