/**
 * PayZen V2-Payment Module version 1.1.0 for ShopWare 4.x-5.x. Support contact : support@payzen.eu.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  payment
 * @package   payzen
 * @author    Lyra Network (http://www.lyra-network.com/)
 * @copyright 2014-2016 Lyra Network and contributors
 * @license   http://www.gnu.org/licenses/agpl.html  GNU Affero General Public License (AGPL v3)
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

		if(
				me.record
				&& me.record.getPayment() instanceof Ext.data.Store
				&& me.record.getPayment().first() instanceof Ext.data.Model
				&& me.record.getPayment().first().get('name') == 'payzen'
		) {
			return [
					{ name:'referer', fieldLabel:me.snippets.details.referer },
					{ name:'remoteAddressConverted', fieldLabel:me.snippets.details.remoteAddress },
					{ name:'deviceTypeHuman', fieldLabel:me.snippets.details.deviceType },

					{ name:'attribute[attribute1]', fieldLabel:'{s name="payzen/overview/details/text_1"}Message{/s}' },
					{ name:'attribute[attribute2]', fieldLabel:'{s name="payzen/overview/details/text_2"}Transaction type{/s}' },
					{ name:'attribute[attribute3]', fieldLabel:'{s name="payzen/overview/details/text_3"}Transaction ID{/s}' },
					{ name:'attribute[attribute4]', fieldLabel:'{s name="payzen/overview/details/text_4"}Payment mean{/s}' },
					{ name:'attribute[attribute5]', fieldLabel:'{s name="payzen/overview/details/text_5"}Card number{/s}' },
					{ name:'attribute[attribute6]', fieldLabel:'{s name="payzen/overview/details/text_6"}Expiration date{/s}' }
			];
		} else {
			return me.callOverridden(arguments);
		}
	}
});
//{/block}
