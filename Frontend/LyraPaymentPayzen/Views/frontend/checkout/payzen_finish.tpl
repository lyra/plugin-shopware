{*
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for ShopWare. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   http://www.gnu.org/licenses/agpl.html GNU Affero General Public License (AGPL v3)
*}

{namespace name="frontend/checkout/payzen_finish"}
{extends file="frontend/checkout/finish.tpl"}

{block name="frontend_index_content" prepend}
    {if $payzenGoingIntoProductionInfo}
        {assign var="payzenGoingIntoProductionInfo" value="{s name='payzen/going_into_production_info'}<b>GOING INTO PRODUCTION :</b>You want to know how to put your shop into production mode, please read chapters « Proceeding to test phase » and « Shifting the shop to production mode » in the documentation of the module. {/s}"}

        {if "frontend/_includes/messages.tpl"|template_exists}
            {include file="frontend/_includes/messages.tpl" type="success" content="$payzenGoingIntoProductionInfo"}
        {else}
            <div class="success bold" style="text-align: left !important; margin-right: 10px;">
                {$payzenGoingIntoProductionInfo}
            </div>
        {/if}
    {/if}

    {if $payzenCheckUrlWarn}
        {if $payzenShopOffline === true}
            {assign var="$payzenMaintenanceModeWarn" value="{s name="payzen/maintenance_mode_warn"}The shop is in maintenance mode. The automatic notification cannot work.{/s}"}

            {if "frontend/_includes/messagess.tpl"|template_exists}
                {include file="frontend/_includes/messages.tpl" type="warning" content="$payzenMaintenanceModeWarn"}
            {else}
                <div class="notice bold" style="text-align: left !important; margin-right: 10px;">
                    {$payzenMaintenanceModeWarn}
                </div>
            {/if}
        {else}
            {assign var="payzenCheckUrlWarn" value="{s name="payzen/check_url_warn"}The automatic validation has not worked. Have you correctly set up the notification URL in your PayZen Back Office ?{/s}"}
            {assign var="payzenCheckUrlDetails" value="<br />{s name="payzen/check_url_warn_details"}For understanding the problem, please read the documentation of the module :<br />&nbsp;&nbsp;&nbsp;- Chapter « To read carefully before going further »<br />&nbsp;&nbsp;&nbsp;- Chapter « Notification URL settings »{/s}"}

            {if "frontend/_includes/messages.tpl"|template_exists}
                {include file="frontend/_includes/messages.tpl" type="warning" content="$payzenCheckUrlWarn $payzenCheckUrlDetails"}
            {else}
                <div class="notice bold" style="text-align: left !important; margin-right: 10px;">
                    {$payzenCheckUrlWarn} {$payzenCheckUrlDetails}
                </div>
            {/if}
        {/if}
    {/if}
{/block}