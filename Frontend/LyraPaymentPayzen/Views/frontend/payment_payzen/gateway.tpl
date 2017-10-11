{*
 * PayZen V2-Payment Module version 1.1.1 for ShopWare 4.x-5.x. Support contact : support@payzen.eu.
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
 * @copyright 2014-2017 Lyra Network and contributors
 * @license   http://www.gnu.org/licenses/agpl.html  GNU Affero General Public License (AGPL v3)
 * @category  payment
 * @package   payzen
*}

{namespace name="frontend/payment_payzen/gateway"}
{extends file='frontend/index/index.tpl'}

{block name='frontend_index_content_left'}{/block}

{* Breadcrumb *}
{block name='frontend_index_start' append}
    {$sBreadcrumb[] = ["name" => "PayZen", "link" => "javascript: void(0);"]}
{/block}

{* Main content *}
{block name="frontend_index_content"}
    <div id="payment" class="grid_20" style="margin: 10px 0 10px 20px; width: 959px;">
        <h3>{s name="payzen/redirect_title"}Payment by bank card{/s}</h3>

        <form action="{$PayzenAction}" method="POST" id="payzen_form" name="payzen_form">
            {$PayzenParams}

            <p>
                <img src="{$base_dir}engine/Shopware/Plugins/Community/Frontend/LyraPaymentPayzen/Views/frontend/_resources/images/payzen_cards.png" alt="PayZen" style="margin-bottom: 5px" />
                <br />

                {s name="payzen/redirect_wait_text"}Please wait, you will be redirected to the payment platform.{/s}
                <br /><br />

                {s name="payzen/redirect_click_text"}If nothing happens in 10 seconds, please click the button below.{/s}
                <br /><br />

                <input type="submit" name="submitPayment" value="{s name='payzen/redirect_button_text'}Pay now{/s}" />
            </p>
        </form>

        <script type="text/javascript">
            {literal}
                window.onload = function() {
                    setTimeout(function() {
                        document.getElementById('payzen_form').submit();
                    }, 1000);
                };
            {/literal}
        </script>
    </div>

    <div class="doublespace">&nbsp;</div>
{/block}
