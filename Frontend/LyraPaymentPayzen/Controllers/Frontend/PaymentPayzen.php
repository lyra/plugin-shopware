<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for ShopWare. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   http://www.gnu.org/licenses/agpl.html GNU Affero General Public License (AGPL v3)
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
            return array(
                'process',
                'success',
                'cancel'
            );
        }
    }
} else {
    abstract class AbstractPaymentPayzen extends Shopware_Controllers_Frontend_Payment {}
}

/**
 * Gateway payment controller.
 */
class Shopware_Controllers_Frontend_PaymentPayzen extends AbstractPaymentPayzen
{

    protected $logger;

    private function getLogger()
    {
        if (! $this->logger) {
            $this->logger = new PayzenLogger(__CLASS__);
        }

        return $this->logger;
    }

    /**
     * Index action method.
     * Forwards to the correct action.
     */
    public function indexAction()
    {
        if ($this->getPaymentShortName() === 'payzen') {
            $this->forward('gateway');
        } else {
            $this->redirect(array('controller' => 'checkout'));
        }
    }

    /**
     * Gateway action method. Collects the payment information and transmit it to the payment provider.
     */
    public function gatewayAction()
    {
        require_once dirname(dirname(dirname(__FILE__))) . '/Components/PayzenRequest.php';
        $request = new PayzenRequest();

        $config = $this->Plugin()->Config();

        // Get current user info.
        $user = $this->getUser();

        // Get current currency.
        $currency = PayzenApi::findCurrencyByAlphaCode($this->getCurrencyShortName());

        // Get current language.
        $lang = '';
        if (version_compare(Shopware()->Config()->version, '5.7', '>=')) {
            $lang = substr(strtolower(Shopware()->Shop()->getLocale()->getLocale()), 0, 2);
        } else {
            $lang = strtolower(Shopware()->Locale()->getLanguage());
        }

        $payzenLang = PayzenApi::isSupportedLanguage($lang) ? $lang : $config->get('payzen_language');

        // Disable 3DS?
        $threedsMpi = null;
        if ($config->get('payzen_3ds_min_amount') && ($this->getAmount() < $config->get('payzen_3ds_min_amount'))) {
            $threedsMpi = '2';
        }

        $version = PayzenTools::getDefault('CMS_IDENTIFIER') . '_v' .  PayzenTools::getDefault('PLUGIN_VERSION');
        $predictedOrderId = Shopware()->Db()->fetchOne("SELECT number FROM s_order_number WHERE name = 'invoice'") + 1 ;

        $params = array(
            'amount' => $currency->convertAmountToInteger($this->getAmount()),
            'currency' => $currency->getNum(),
            'language' => $payzenLang,
            'contrib' => $version . '/' . Shopware()->Config()->version . '/' . PHP_VERSION,
            'order_id' => $predictedOrderId,

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

        $request->addExtInfo('session_id', session_id());
        $request->addExtInfo('shop_id', $user['additional']['user']['subshopID']);
        $request->addExtInfo('unique_id', $this->createPaymentUniqueId()); // Unique ID to be assigned to order if payment successful.

        // Shopware 5.6+ supports session restoring
        if (version_compare(Shopware()->Config()->get('version'), '5.6.3', '>=')){
            $this->getLogger()->info('Session token generation.');
            $token = $this->createPaymentToken();

            if ($token !== null) {
                $request->addExtInfo(Shopware\Components\Cart\PaymentTokenService::TYPE_PAYMENT_TOKEN, $token);
            }
        }

        // Process billing and shipping states.
        if (! empty($user['additional']['state'])) {
            $request->set('cust_state', $user['additional']['state']['shortcode']);
        }

        if (! empty($user['additional']['stateShipping'])) {
            $request->set('ship_to_state', $user['additional']['stateShipping']['shortcode']);
        }

        // Set module admin parameters.
        $keys = array(
            'site_id', 'key_test', 'key_prod', 'ctx_mode', 'platform_url',
            'available_languages', 'capture_delay', 'validation_mode', 'payment_cards',
            'redirect_enabled', 'return_mode', 'redirect_success_timeout',
            'redirect_success_message', 'redirect_error_timeout', 'redirect_error_message',
            'sign_algo'
        );

        foreach ($keys as $key) {
            $value = $config->get('payzen_' . $key);

            if (is_a($value, 'Enlight_Config')) { // Case of available_languages or payment_cards.
                $value = implode(';', $value->toArray());
            }

            $request->set($key, $value);
        }

        $this->getLogger()->info('Client ' . $user['additional']['user']['email'] . ' sent to payment gateway.');
        $this->getLogger()->debug('Form data : ' . print_r($request->getRequestFieldsArray(true), true));

        $this->View()->PayzenParams = $request->getRequestHtmlFields();
        $this->View()->PayzenAction = $request->get('platform_url');
    }

    protected function restoreShopwareSession($sessionId)
    {
        if (version_compare(Shopware()->Config()->get('version'), '5.7.0', '>=')) {
            Shopware()->Session()->save();
            Shopware()->Session()->setId($sessionId);
            Shopware()->Session()->start();
        } else {
            \Enlight_Components_Session::writeClose();
            \Enlight_Components_Session::setId($sessionId);
            \Enlight_Components_Session::start();
        }
    }

    /**
     * Process action method. Manages payment result for IPN and client calls.
     */
    public function processAction()
    {
        require_once dirname(dirname(dirname(__FILE__))) . '/Components/PayzenResponse.php';

        // Reset real name for parameter vads_order_id after avoiding ShopWare filter on "s_order_"-like strings.
        if ($this->Request()->getParam('tmp_order_id')) {
            $this->Request()->setParam('vads_order_id', $this->Request()->getParam('tmp_order_id'));
        }

        // Restore initial session for an IPN call.
        if ($this->Request()->getParam('vads_hash')
            && ($sessionId = $this->Request()->getParam('vads_ext_info_session_id'))) {
            // Restore Shopware session.
            $this->restoreShopwareSession($sessionId);

            // Restore active shop.
            $repository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop');
            $shop = $repository->getActiveById($this->Request()->getParam('vads_ext_info_shop_id'));
            $shop->registerResources(Shopware()->Bootstrap());

            // For backward compatibility.
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

        // Check the authenticity of the request.
        if (! $payzenResponse->isAuthentified()) {
            $ip = $this->Request()->getClientIp(false);

            $this->getLogger()->error("{$ip} tries to access payment_payzen/process page without valid signature with parameters: " . print_r($params, true));
            $this->getLogger()->error('Signature algorithm selected in module settings must be the same as one selected in PayZen Back Office.');

            if ($fromServer) {
                die($payzenResponse->getOutputForGateway('auth_fail'));
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

        // Search for temporary order.
        $userIdSql = '';
        $sqlParams = array(
            '', // Empty transactionID.
            session_id()
        );

        if ($cust_id = $payzenResponse->get('cust_id')) {
            $userIdSql = 'AND `userID` = ? ';
            $sqlParams[] = $cust_id;
        }

        $sql = 'SELECT `id` FROM `s_order` WHERE `transactionID` = ? AND `temporaryID` = ? ' . $userIdSql . 'AND `status` = -1';
        $tmpOrderId = Shopware()->Db()->fetchOne($sql, $sqlParams);

        $paymentUniqueId = $this->Request()->getParam('vads_ext_info_unique_id');

        if (isset($tmpOrderId) && ! empty($tmpOrderId)) {
            // Temporary order exists in DB, order not yet processed.
            $this->getLogger()->info('Temporary order found in database. New payment processing.');

            if ($payzenResponse->isAcceptedPayment()) {
                $newPaymentStatus = $this->getNewOrderPaymentStatus($payzenResponse);

                $this->getLogger()->info("Payment accepted. New order payment status is {$newPaymentStatus}.");

                // Create final order and update payment status.
                $orderNumber = $this->saveOrder(
                    $payzenResponse->get('trans_id'),
                    $paymentUniqueId,
                    $newPaymentStatus,
                    false
                );

                // Get the new order ID by order number.
                $orderId = Shopware()->Db()->fetchOne('SELECT `id` FROM `s_order` WHERE `ordernumber` = ?', array($orderNumber));

                // Update payment data.
                $this->updateOrderInfo($orderId, $payzenResponse);

                // Save payment unique ID to session.
                Shopware()->Session()->paymentUniqueId = $paymentUniqueId;

                if ($fromServer) {
                    die($payzenResponse->getOutputForGateway('payment_ok'));
                } else {
                    if ($config->get('payzen_ctx_mode') === 'TEST') {
                        Shopware()->Session()->payzenCheckUrlWarn = true;
                    }

                    $this->forward('success');
                }
            } else {
                // Payment not accepted.
                $this->getLogger()->info('Payment failed or cancelled. No order creation.');

                if ($fromServer) {
                    die($payzenResponse->getOutputForGateway('payment_ko_bis'));
                } else {
                    Shopware()->Session()->payzenPaymentResult = $payzenResponse->isCancelledPayment() ? 'CANCEL' : 'ERROR';
                    $this->forward('cancel');
                }
            }
        } else {
            // Order already processed or no order at all.

            // Search for final order.
            $sql = 'SELECT `id`, `cleared` FROM `s_order` WHERE `transactionID` = ? AND `temporaryID` = ? AND `userID` = ?';
            $orderInfo = Shopware()->Db()->fetchRow($sql, array(
                $payzenResponse->get('trans_id'),
                $paymentUniqueId, // Payment unique ID sent to the gateway.
                $payzenResponse->get('cust_id')
            ));

            if (isset($orderInfo['id']) && ! empty($orderInfo['id'])) {
                // Final order found in database.
                $this->getLogger()->info('Final order found in database. Payment is already processed.');

                $currentPaymentStatus = $orderInfo['cleared'];
                $newPaymentStatus = $this->getNewOrderPaymentStatus($payzenResponse);

                if ($currentPaymentStatus == $newPaymentStatus) {
                    $this->getLogger()->info('Payment result is confirmed, just show appropriate message or redierct to result page.');

                    if ($fromServer) {
                        $msg = $payzenResponse->isAcceptedPayment() ? 'payment_ok_already_done' : 'payment_ko_already_done';
                        die($payzenResponse->getOutputForGateway($msg));
                    } else {
                        $page = $payzenResponse->isAcceptedPayment() ? 'success' : 'cancel';
                        if (! $payzenResponse->isAcceptedPayment()) {
                            Shopware()->Session()->payzenPaymentResult = $payzenResponse->isCancelledPayment() ? 'CANCEL' : 'ERROR';
                        }

                        $this->forward($page);
                    }
                } else {
                    if ($fromServer) {
                        // Payment notification URL replay, update payment status.
                        $this->getLogger()->info('Notification URL replay: payment result is updated, let\'s save it to order.');

                        $this->savePaymentStatus(
                            $payzenResponse->get('trans_id'),
                            $paymentUniqueId,
                            $newPaymentStatus,
                            true
                        );

                        // Update payment data.
                        $this->updateOrderInfo($orderInfo['id'], $payzenResponse);

                        $msg = $payzenResponse->isAcceptedPayment() ? 'payment_ok' : 'payment_ko';
                        die($payzenResponse->getOutputForGateway($msg));
                    } else {
                        // Inconsistent payment result, redirect to account order list page.
                        $this->getLogger()->error('Error: payment result is different from saved order payment status.');

                        $this->redirect(array(
                            'controller' => 'account',
                            'action' => 'orders'
                        ));
                    }
                }
            } else {
                // No final nor temporary order.
                $this->getLogger()->error("Neither final nor temporary order found for client {$payzenResponse->get('cust_email')} in database.");

                if ($fromServer) {
                    die($payzenResponse->getOutputForGateway('order_not_found'));
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
     * Success action method. Redirects to succes page after payment.
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
     * Cancel action method. Manages cancelled and failed payments.
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
     * Return the ShopWare payment status corresponding to gateway payment result.
     *
     * @param PayzenResponse $payzenResponse
     * @return int
     */
    private function getNewOrderPaymentStatus(PayzenResponse $payzenResponse)
    {
        switch ($payzenResponse->get('trans_status')) {
            case 'WAITING_AUTHORISATION':
            case 'UNDER_VERIFICATION':
            case 'INITIAL':
            case 'WAITING_FOR_PAYMENT':
            case 'AUTHORISED_TO_VALIDATE':
            case 'WAITING_AUTHORISATION_TO_VALIDATE':
                return '21'; // Review necessary.

            case 'AUTHORISED':
            case 'CAPTURE_FAILED':
            case 'CAPTURED':
            case 'ACCEPTED':
                return $this->Plugin()->Config()->get('payzen_payment_status_on_success');

            case 'CANCELLED':
            case 'ABANDONED':
            case 'EXPIRED':
            case 'NOT_CREATED':
            case 'REFUSED':

            default :
                return '17'; // Open.
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
        // Update "internalcomment" field.
        $sql = 'UPDATE `s_order` SET `internalcomment` = ?, `status` = 1';
        if ($payzenResponse->isAcceptedPayment() && ! $payzenResponse->isPendingPayment()) {
            // Update "cleareddate" for a complete order payment.
            $sql .= ', `cleareddate` = NOW()';
        }

        $sql .= ' WHERE `id` = ?';
        Shopware()->Db()->query($sql, array($payzenResponse->getMessage(), $orderId));

        // Save payment details.
        $expiry = '';
        if ($payzenResponse->get('expiry_month') && $payzenResponse->get('expiry_year')) {
            $expiry = str_pad($payzenResponse->get('expiry_month'), 2, '0', STR_PAD_LEFT) . ' / ' . $payzenResponse->get('expiry_year');
        }

        $locale = '';
        if (version_compare(Shopware()->Config()->version, '5.7', '>=')) {
            $locale = Shopware()->Shop()->getLocale()->getLocale();
        } else {
            $locale = Shopware()->Locale()->toString();
        }

        $brandChoiceMessages = array(
            'user_choice' => array(
                'de_DE' => 'Kartenmarke von Käufer gewählt',
                'en_GB' => 'Card brand chosen by buyer',
                'fr_FR' => 'Marque de carte choisie par l\'acheteur',
                'es_ES' => 'Marca de tarjeta elegida por el comprador'
            ),
            'default_choice' => array(
                'de_DE' => 'Standard-Kartenmarke verwendet',
                'en_GB' => 'Default card brand used',
                'fr_FR' => 'Marque de carte par défaut utilisée',
                'es_ES' => 'Marca de tarjeta predeterminada en uso'
            )
        );

        $cardBrand = $payzenResponse->get('card_brand');
        if ($payzenResponse->get('brand_management')) {
            $brandInfo = json_decode($payzenResponse->get('brand_management'));

            if (isset($brandInfo->userChoice) && $brandInfo->userChoice) {
                $brandChoice = key_exists($locale, $brandChoiceMessages['user_choice']) ? $brandChoiceMessages['user_choice'][$locale] : 'Card brand chosen by buyer';
            } else {
                $brandChoice = key_exists($locale, $brandChoiceMessages['default_choice']) ? $brandChoiceMessages['default_choice'][$locale] : 'Default card brand used';
            }

            $cardBrand .= ' (' . $brandChoice . ')';
        }

        $paymentInfo = array(
            'attribute1' => $payzenResponse->getMessage(),
            'attribute2' => $payzenResponse->get('operation_type'),
            'attribute3' => $payzenResponse->get('trans_id'),
            'attribute4' => $cardBrand,
            'attribute5' => $payzenResponse->get('card_number'),
            'attribute6' => $expiry
        );
        Shopware()->Db()->update('s_order_attributes', $paymentInfo, '`orderID` = ' . $orderId);
    }

    /**
     * @return string|null
     */
    public function createPaymentToken()
    {
        if ($this->container->has(Shopware\Components\Cart\PaymentTokenService::class)) {
            return $this->container->get(Shopware\Components\Cart\PaymentTokenService::class)->generate();
        }

        return null;
    }
}
