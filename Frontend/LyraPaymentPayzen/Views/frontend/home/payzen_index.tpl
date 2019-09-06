{*
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for ShopWare. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   http://www.gnu.org/licenses/agpl.html GNU Affero General Public License (AGPL v3)
*}

{namespace name="frontend/checkout/payzen_index"}
{extends file="frontend/checkout/home.tpl"}

{block name="frontend_index_content_top" prepend}
    {assign var="payzenErrorMsg" value="{s name="payzen/payment_fatal"}An error has occurred during the payment process.{/s}"}

    {if "frontend/_includes/messages.tpl"|template_exists}
        {include file="frontend/_includes/messages.tpl" type="error" content="$payzenErrorMsg"}
    {else}
        <div class="error bold" style="text-align: left !important; margin-right: 10px;">
            {$payzenErrorMsg}
        </div>
    {/if}
{/block}