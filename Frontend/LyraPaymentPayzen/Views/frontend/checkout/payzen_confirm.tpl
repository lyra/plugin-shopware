{*
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
*}

{namespace name="frontend/checkout/payzen_confirm"}
{extends file="frontend/checkout/confirm.tpl"}

{block name="frontend_checkout_confirm_error_messages" prepend}
	{if $payzenPaymentResult == 'ERROR'}
		{assign var="payzenErrorMsg" value="{s name="payzen/payment_error"}Your order has not been confirmed. The payment has not been accepted.{/s}"}

		{if "frontend/_includes/messages.tpl"|template_exists}
			{include file="frontend/_includes/messages.tpl" type="error" content="$payzenErrorMsg"}
		{else}
			<div class="error bold" style="text-align: left !important; margin-right: 10px;">
				{$payzenErrorMsg}
			</div>
		{/if}
	{elseif $payzenPaymentResult == 'CANCEL'}
		{assign var="payzenCancelMsg" value="{s name="payzen/payment_cancel"}Checkout have been canceled.{/s}"}

		{if "frontend/_includes/messages.tpl"|template_exists}
			{include file="frontend/_includes/messages.tpl" type="warning" content="$payzenCancelMsg"}
		{else}
			<div class="notice bold" style="text-align: left !important; margin-right: 10px;">
				{$payzenCancelMsg}
			</div>
		{/if}
	{/if}
{/block}
