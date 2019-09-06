{*
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for ShopWare. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   http://www.gnu.org/licenses/agpl.html GNU Affero General Public License (AGPL v3)
*}

{namespace name="frontend/checkout/payzen_confirm"}
{extends file="frontend/checkout/confirm.tpl"}

{block name="frontend_checkout_confirm_error_messages" prepend}
    {if $payzenPaymentResult === 'ERROR'}
        {assign var="payzenErrorMsg" value="{s name="payzen/payment_error"}Your payment was not accepted. Please, try to re-order.{/s}"}

        {if "frontend/_includes/messages.tpl"|template_exists}
            {include file="frontend/_includes/messages.tpl" type="error" content="$payzenErrorMsg"}
        {else}
            <div class="error bold" style="text-align: left !important; margin-right: 10px;">
                {$payzenErrorMsg}
            </div>
        {/if}
    {elseif $payzenPaymentResult === 'CANCEL'}
        {assign var="payzenCancelMsg" value="{s name="payzen/payment_cancel"}The payment have been canceled.{/s}"}

        {if "frontend/_includes/messages.tpl"|template_exists}
            {include file="frontend/_includes/messages.tpl" type="warning" content="$payzenCancelMsg"}
        {else}
            <div class="notice bold" style="text-align: left !important; margin-right: 10px;">
                {$payzenCancelMsg}
            </div>
        {/if}
    {/if}
{/block}