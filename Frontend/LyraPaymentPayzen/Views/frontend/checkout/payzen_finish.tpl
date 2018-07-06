{*
 * PayZen V2-Payment Module version 1.2.0 for ShopWare 4.x-5.x. Support contact : support@payzen.eu.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Lyra Network (http://www.lyra-network.com/)
 * @copyright 2014-2018 Lyra Network and contributors
 * @license   http://www.gnu.org/licenses/agpl.html  GNU Affero General Public License (AGPL v3)
 * @category  payment
 * @package   payzen
*}

{namespace name="frontend/checkout/payzen_finish"}
{extends file="frontend/checkout/finish.tpl"}

{block name="frontend_index_content" prepend}
    {if $payzenGoingIntoProductionInfo}
        {assign var="payzenGoingIntoProductionInfo" value="{s name='payzen/going_into_production_info'}<b>GOING INTO PRODUCTION :</b> You want to know how to put your shop into production mode, please go to this URL : {/s}<a href='https://secure.payzen.eu/html/faq/prod' target='_blank'>https://secure.payzen.eu/html/faq/prod</a>"}

        {if "frontend/_includes/messages.tpl"|template_exists}
            {include file="frontend/_includes/messages.tpl" type="success" content="$payzenGoingIntoProductionInfo"}
        {else}
            <div class="success bold" style="text-align: left !important; margin-right: 10px;">
                {$payzenGoingIntoProductionInfo}
            </div>
        {/if}
    {/if}

    {if $payzenCheckUrlWarn}
        {if $payzenShopOffline == true}
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
