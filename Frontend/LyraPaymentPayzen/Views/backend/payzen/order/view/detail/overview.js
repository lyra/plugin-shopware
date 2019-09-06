/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for ShopWare. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   http://www.gnu.org/licenses/agpl.html GNU Affero General Public License (AGPL v3)
 */

//{block name="backend/order/view/detail/overview" append}
//{namespace name="backend/order_payzen/main"}
Ext.define('Shopware.payzen.apps.Order.view.detail.Overview', {

    /**
     * Defines an override applied to a class.
     * @string
     */
    override: 'Shopware.apps.Order.view.detail.Overview',

    createRightDetailElements: function() {
        var me = this;

        if (
            me.record
            && me.record.getPayment() instanceof Ext.data.Store
            && me.record.getPayment().first() instanceof Ext.data.Model
            && me.record.getPayment().first().get('name') === 'payzen'
        ) {
            return [
                { name:'referer', fieldLabel:me.snippets.details.referer },
                { name:'remoteAddressConverted', fieldLabel:me.snippets.details.remoteAddress },
                { name:'deviceTypeHuman', fieldLabel:me.snippets.details.deviceType },

                { name:'attribute[attribute1]', fieldLabel:'{s name="payzen/overview/details/text_1"}Message{/s}' },
                { name:'attribute[attribute2]', fieldLabel:'{s name="payzen/overview/details/text_2"}Transaction type{/s}' },
                { name:'attribute[attribute3]', fieldLabel:'{s name="payzen/overview/details/text_3"}Transaction ID{/s}' },
                { name:'attribute[attribute4]', fieldLabel:'{s name="payzen/overview/details/text_4"}Means of payment{/s}' },
                { name:'attribute[attribute5]', fieldLabel:'{s name="payzen/overview/details/text_5"}Card number{/s}' },
                { name:'attribute[attribute6]', fieldLabel:'{s name="payzen/overview/details/text_6"}Expiration date{/s}' }
            ];
        } else {
            return me.callOverridden(arguments);
        }
    }
});
//{/block}
