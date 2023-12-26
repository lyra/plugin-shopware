/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

const { Component, Mixin } = Shopware;
import template from './sw-order-detail-details.html.twig';
import './sw-order-detail-details.scss';

Component.override('sw-order-detail-details', {
    template,

    methods: {
        isLyraPayment(transaction) {
            if (! transaction.customFields) {
                return false;
            }

            return transaction.customFields.lyra_transaction_id;
        },

        hasLyraPayment(order) {
            let me = this;
            let isLyra = false;

            if (! order.transactions) {
                return false;
            }

            order.transactions.map(function(transaction) {
                if (me.isLyraPayment(transaction)) {
                    isLyra = true;
                }
            });

            return isLyra;
        }
    }
});