{*
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for ShopWare. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   http://www.gnu.org/licenses/agpl.html GNU Affero General Public License (AGPL v3)
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

                {s name="payzen/redirect_wait_text"}Please wait, you will be redirected to the payment gateway.{/s}
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