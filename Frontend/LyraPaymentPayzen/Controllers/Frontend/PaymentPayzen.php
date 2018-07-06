<?php
/**
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
 */

require_once dirname(dirname(dirname(__FILE__))) . '/Components/PayzenLogger.php';
require_once dirname(dirname(dirname(__FILE__))) . '/Components/PayzenTools.php';

if (interface_exists('Shopware\Components\CSRFWhitelistAware')) {
    abstract class AbstractPaymentPayzen extends Shopware_Controllers_Frontend_Payment
        implements Shopware\Components\CSRFWhitelistAware
    {

        /**
         * Returns a list with actions which should not be validated for CSRF protection.
         *
         * @return string[]
         */
        public function getWhitelistedCSRFActions()
        {
            return array('process');
        }
    }
} else {
    abstract class AbstractPaymentPayzen extends Shopware_Controllers_Frontend_Payment {}
}

/**
 * PayZen payment controller
 */
class Shopware_Controllers_Frontend_PaymentPayzen extends AbstractPaymentPayzen
{

    protected $logger;

    public function __construct(Enlight_Controller_Request_Request $request, Enlight_Controller_Response_Response $response)
    {
        parent::__construct($request, $response);

        $this->logger = new PayzenLogger(__CLASS__);
    }

    /**
     * Index action method.
     * Forwards to the correct action.
     */
    public function indexAction()
    {
        if ($this->getPaymentShortName() == 'payzen') {
            $this->forward('gateway');
        } else {
            $this->redirect(array('controller' => 'checkout'));
        }
    }

    /**
     * Gateway action method.
     * Collects the payment information and transmit it to the payment provider.
     */
    public function gatewayAction()
    {
        require_once dirname(dirname(dirname(__FILE__))) . '/Components/PayzenRequest.php';
        $request = new PayzenRequest();

        $config = $this->Plugin()->Config();

        // get current user info
        $user = $this->getUser();

        // get current currency
        $currency = PayzenApi::findCurrencyByAlphaCode($this->getCurrencyShortName());

        // get current language
        $lang = strtolower(Shopware()->Locale()->getLanguage());
        $payzenLang = PayzenApi::isSupportedLanguage($lang) ? $lang : $config->get('payzen_language');

        // activate 3ds ?
        $threedsMpi = null;
        if ($config->get('payzen_3ds_min_amount') && ($this->getAmount() < $config->get('payzen_3ds_min_amount'))) {
            $threedsMpi = '2';
        }

        $predictedOrderId = Shopware()->Db()->fetchOne("SELECT number FROM s_order_number WHERE name = 'invoice'") + 1 ;
        $params = array(
            'amount' => $currency->convertAmountToInteger($this->getAmount()),
            'currency' => $currency->getNum(),
            'language' => $payzenLang,
            'contrib' => 'ShopWare4.x-5.x_1.2.0/' . Shopware::VERSION . '/' . PHP_VERSION,
            'order_id' => $predictedOrderId,
            'order_info' => 'session_id=' . \Enlight_Components_Session::getId() . '&shop_id=' . $user['additional']['user']['subshopID'],
            'order_info2' => 'unique_id=' . $this->createPaymentUniqueId(), // unique ID to be assigned to order if payment successful

            'cust_id' => isset($user['additional']['user']['customerId']) ? $user['additional']['user']['customerId'] : $user['additional']['user']['userID'],
            'cust_email' => $user['additional']['user']['email'],

            'cust_title' => $user['billingaddress']['salutation'],
            'cust_first_name' => $user['billingaddress']['firstname'],
            'cust_last_name' => $user['billingaddress']['lastname'],
            'cust_address' => $user['billingaddress']['streetnumber'] . ' ' . $user['billingaddress']['street'],
            'cust_zip' => $user['billingaddress']['zipcode'],
            'cust_city' => $user['billingaddress']['city'],
            'cust_country' => $user['additional']['country']['countryiso'],
            'cust_phone' => $user['billingaddress']['phone'],

            'ship_to_first_name' => $user['shippingaddress']['firstname'],
            'ship_to_last_name' => $user['shippingaddress']['lastname'],
            'ship_to_street' => $user['shippingaddress']['streetnumber'] . ' ' . $user['shippingaddress']['street'],
            'ship_to_zip' => $user['shippingaddress']['zipcode'],
            'ship_to_city' => $user['shippingaddress']['city'],
            'ship_to_country' => $user['additional']['countryShipping']['countryiso'],
            'ship_to_phone_num' => $user['billingaddress']['phone'],

            'threeds_mpi' => $threedsMpi,

            'url_return'  => $this->Front()->Router()->assemble(array('action' => 'process', 'forceSecure' => true))
        );
        $request->setFromArray($params);

        // process billing and shipping states
        if (! empty($user['additional']['state'])) {
            $request->set('cust_state', $user['additional']['state']['shortcode']);
        }

        if (! empty($user['additional']['stateShipping'])) {
            $request->set('ship_to_state', $user['additional']['stateShipping']['shortcode']);
        }

        // set module admin parameters
        $keys = array(
            'site_id', 'key_test', 'key_prod', 'ctx_mode', 'platform_url',
            'available_languages', 'capture_delay', 'validation_mode', 'payment_cards',
            'redirect_enabled', 'return_mode', 'redirect_success_timeout',
            'redirect_success_message', 'redirect_error_timeout', 'redirect_error_message',
            'sign_algo'
        );

        foreach ($keys as $key) {
            $value = $config->get('payzen_' . $key);

            if (is_a($value, 'Enlight_Config')) { // case available_languages or payment_cards
                $value = implode(';', $value->toArray());
            }

            $request->set($key, $value);
        }

        $this->logger->info('Client ' . $user['additional']['user']['email'] . ' sent to payment gateway.');
        $this->logger->debug('Form data : ' . print_r($request->getRequestFieldsArray(true), true));

        $this->View()->PayzenParams = $request->getRequestHtmlFields();
        $this->View()->PayzenAction = $request->get('platform_url');
    }

    /**
     * Process action method.
     * Manages payment result for server and client calls.
     */
    public function processAction()
    {
        require_once dirname(dirname(dirname(__FILE__))) . '/Components/PayzenResponse.php';

        // reset real name for parameter vads_order_id after avoiding ShopWare filter on "s_order_"-like strings
        if ($this->Request()->getParam('tmp_order_id')) {
            $this->Request()->setParam('vads_order_id', $this->Request()->getParam('tmp_order_id'));
        }

        // reset real name for parameter vads_order_info after avoiding ShopWare filter on "s_order_"-like strings
        if ($this->Request()->getParam('tmp_order_info')) {
            $this->Request()->setParam('vads_order_info', $this->Request()->getParam('tmp_order_info'));
        }

        // reset real name for parameter vads_order_info2 after avoiding ShopWare filter on "s_order_"-like strings
        if ($this->Request()->getParam('tmp_order_info2')) {
            $this->Request()->setParam('vads_order_info2', $this->Request()->getParam('tmp_order_info2'));
        }

        // restore initial session for a server call
        if ($this->Request()->getParam('vads_hash') && ($info = $this->Request()->getParam('vads_order_info'))) {
            $data = explode('&', $info);

            $sessionId = substr($data[0], strlen('session_id='));

            \Enlight_Components_Session::writeClose();
            \Enlight_Components_Session::setId($sessionId);
            \Enlight_Components_Session::start();

            // restore active shop
            $repository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop');
            $shopId = substr($data[1], strlen('shop_id='));
            $shop = $repository->getActiveById($shopId);
            $shop->registerResources(Shopware()->Bootstrap());

            // for backward compatibility
            Shopware()->System()->sSESSION = $_SESSION;
            Shopware()->System()->sSESSION_ID = $sessionId;

            Shopware()->Modules()->setSystem(Shopware()->System());
        }

        $params = $this->Request()->getParams();
        $config = $this->Plugin()->Config();

        $payzenResponse = new PayzenResponse(
            $params,
            $config->get('payzen_ctx_mode'),
            $config->get('payzen_key_test'),
            $config->get('payzen_key_prod'),
            $config->get('payzen_sign_algo')
        );

        $fromServer = ($payzenResponse->get('hash') != null);

        // check the authenticity of the request
        if (! $payzenResponse->isAuthentified()) {
            $ip = $this->Request()->getClientIp(false);

            $this->logger->error("{$ip} tries to access payment_payzen/process page without valid signature with parameters: " . print_r($params, true));
            $this->logger->error('Signature algorithm selected in module settings must be the same as one selected in PayZen Back Office.');

            if ($fromServer) {
                die($payzenResponse->getOutputForPlatform('auth_fail'));
            } else {
                Shopware()->Modules()->Basket()->clearBasket();
                Shopware()->Modules()->Basket()->sRefreshBasket();

                Shopware()->Session()->payzenPaymentError = true;
                $this->redirect(array('controller' => 'index'));
                return;
            }
        }

        if (! $fromServer && ($config->get('payzen_ctx_mode') === 'TEST') && PayzenTools::$pluginFeatures['prodfaq']) {
            Shopware()->Session()->payzenGoingIntoProductionInfo = true;
        }

        // search for temporary order
        $sql = 'SELECT `id` FROM `s_order` WHERE `transactionID` = ? AND `temporaryID` = ? AND `userID` = ? AND `status` = -1';
        $tmpOrderId = Shopware()->Db()->fetchOne($sql, array(
            '', // empty transactionID
            \Enlight_Components_Session::getId(),
            $payzenResponse->get('cust_id')
        ));

        $paymentUniqueId = substr($payzenResponse->get('order_info2'), strlen('unique_id='));

        if (isset($tmpOrderId) && ! empty($tmpOrderId)) {
            // temporary order exists in DB, order not yet processed
            $this->logger->info('Temporary order found in database. New payment processing.');

            if ($payzenResponse->isAcceptedPayment()) {
                $newPaymentStatus = $this->getNewOrderPaymentStatus($payzenResponse);

                $this->logger->info("Payment accepted. New order payment status is {$newPaymentStatus}.");

                // create final order and update payment status
                $orderNumber = $this->saveOrder(
                    $payzenResponse->get('trans_id'),
                    $paymentUniqueId,
                    $newPaymentStatus,
                    false
                );

                // get the new order ID by order number
                $orderId = Shopware()->Db()->fetchOne('SELECT `id` FROM `s_order` WHERE `ordernumber` = ?', array($orderNumber));

                // update payment data
                $this->updateOrderInfo($orderId, $payzenResponse);

                // save payment unique ID to session
                Shopware()->Session()->paymentUniqueId = $paymentUniqueId;

                if ($fromServer) {
                    die($payzenResponse->getOutputForPlatform('payment_ok'));
                } else {
                    if ($config->get('payzen_ctx_mode') == 'TEST') {
                        Shopware()->Session()->payzenCheckUrlWarn = true;
                    }

                    $this->forward('success');
                }
            } else {
                // payment not accepted
                $this->logger->info('Payment failed or cancelled. No order creation.');

                if ($fromServer) {
                    die($payzenResponse->getOutputForPlatform('payment_ko'));
                } else {
                    Shopware()->Session()->payzenPaymentResult = $payzenResponse->isCancelledPayment() ? 'CANCEL' : 'ERROR';
                    $this->forward('cancel');
                }
            }
        } else {
            // order already processed or no order at all

            // search for final order
            $sql = 'SELECT `id`, `cleared` FROM `s_order` WHERE `transactionID` = ? AND `temporaryID` = ? AND `userID` = ?';
            $orderInfo = Shopware()->Db()->fetchRow($sql, array(
                $payzenResponse->get('trans_id'),
                $paymentUniqueId, // payment unique ID sent to the gateway
                $payzenResponse->get('cust_id')
            ));

            if (isset($orderInfo['id']) && ! empty($orderInfo['id'])) {
                // final order found in database
                $this->logger->info('Final order found in database. Payment is already processed.');

                $currentPaymentStatus = $orderInfo['cleared'];
                $newPaymentStatus = $this->getNewOrderPaymentStatus($payzenResponse);

                if ($currentPaymentStatus == $newPaymentStatus) {
                    $this->logger->info('Payment result is confirmed, just show appropriate message or redierct to result page.');

                    if ($fromServer) {
                        $msg = $payzenResponse->isAcceptedPayment() ? 'payment_ok_already_done' : 'payment_ko_already_done';
                        die($payzenResponse->getOutputForPlatform($msg));
                    } else {
                        $page = $payzenResponse->isAcceptedPayment() ? 'success' : 'cancel';
                        if (! $payzenResponse->isAcceptedPayment()) {
                            Shopware()->Session()->payzenPaymentResult = $payzenResponse->isCancelledPayment() ? 'CANCEL' : 'ERROR';
                        }

                        $this->forward($page);
                    }
                } else {
                    if ($fromServer) {
                        // payment notification URL replay, update payment status
                        $this->logger->info('Notification URL replay: payment result is updated, let\'s save it to order.');

                        $this->savePaymentStatus(
                            $payzenResponse->get('trans_id'),
                            $paymentUniqueId,
                            $newPaymentStatus,
                            true
                        );

                        // update payment data
                        $this->updateOrderInfo($orderInfo['id'], $payzenResponse);

                        $msg = $payzenResponse->isAcceptedPayment() ? 'payment_ok' : 'payment_ko';
                        die($payzenResponse->getOutputForPlatform($msg));
                    } else {
                        // inconsistent payment result, redirect to account order list page
                        $this->logger->error('Error: payment result is different from saved order payment status.');

                        $this->redirect(array(
                            'controller' => 'account',
                            'action' => 'orders'
                        ));
                    }
                }
            } else {
                // no final nor temporary order
                $this->logger->error("Neither final nor temporary order found for client {$payzenResponse->get('cust_email')} in database.");

                if ($fromServer) {
                    die($payzenResponse->getOutputForPlatform('order_not_found'));
                } else {
                    Shopware()->Modules()->Basket()->clearBasket();
                    Shopware()->Modules()->Basket()->sRefreshBasket();

                    Shopware()->Session()->payzenPaymentError = true;
                    $this->redirect(array('controller' => 'index'));
                }
            }
        }
    }

    /**
     * Success action method.
     * Redirects to succes page after payment.
     */
    public function successAction()
    {
        $this->redirect(array(
            'controller' => 'checkout',
            'action' => 'finish',
            'sUniqueID' => Shopware()->Session()->paymentUniqueId
        ));
    }

    /**
     * Cancel action method.
     * Manages cancelled and failed payments.
     */
    public function cancelAction()
    {
        $this->redirect(array('controller' => 'checkout', 'action' => 'confirm'));
    }

    /**
     * Return the payment plugin instance.
     *
     * @return Shopware_Plugins_Frontend_LyraPaymentPayzen_Bootstrap
     */
    private function Plugin()
    {
        return Shopware()->Plugins()->Frontend()->LyraPaymentPayzen();
    }

    /**
     * Return the ShopWare payment status corresponding to PayZen payment result.
     *
     * @param PayzenResponse $payzenResponse
     * @return int
     */
    private function getNewOrderPaymentStatus(PayzenResponse $payzenResponse)
    {
        switch ($payzenResponse->get('trans_status')) {
            case 'UNDER_VERIFICATION':
                return '21'; // Verification necessary

            case 'INITIAL':
            case 'WAITING_AUTHORISATION_TO_VALIDATE':
            case 'WAITING_AUTHORISATION':
                return '17'; // Open

            case 'AUTHORISED_TO_VALIDATE':
            case 'AUTHORISED':
            case 'CAPTURE_FAILED':
            case 'CAPTURED':
                return '12'; // Fully paid

            case 'CANCELLED':
            case 'ABANDONED':
            case 'EXPIRED':
            case 'NOT_CREATED':
            case 'REFUSED':

            default :
                return '17'; // Open
        }
    }

    /**
     * Update order internal comment and other payment details.
     *
     * @param int $orderId
     * @param PayzenResponse $payzenResponse
     */
    private function updateOrderInfo($orderId, PayzenResponse $payzenResponse)
    {
        // update internalcomment
        $sql = 'UPDATE `s_order` SET `internalcomment` = ?, `status` = 1';
        if ($payzenResponse->isAcceptedPayment() && ! $payzenResponse->isPendingPayment()) {
            // update cleareddate for a complete order payment
            $sql .= ', `cleareddate` = NOW()';
        }

        $sql .= ' WHERE `id` = ?';
        Shopware()->Db()->query($sql, array($payzenResponse->getMessage(), $orderId));

        // save payment details
        $expiry = '';
        if ($payzenResponse->get('expiry_month') && $payzenResponse->get('expiry_year')) {
            $expiry = str_pad($payzenResponse->get('expiry_month'), 2, '0', STR_PAD_LEFT) . ' / ' . $payzenResponse->get('expiry_year');
        }

        $paymentInfo = array(
            'attribute1' => $payzenResponse->getMessage(),
            'attribute2' => $payzenResponse->get('operation_type'),
            'attribute3' => $payzenResponse->get('trans_id'),
            'attribute4' => $payzenResponse->get('card_brand'),
            'attribute5' => $payzenResponse->get('card_number'),
            'attribute6' => $expiry
        );
        Shopware()->Db()->update('s_order_attributes', $paymentInfo, '`orderID` = ' . $orderId);
    }
}
