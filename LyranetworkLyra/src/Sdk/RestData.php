<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

namespace Lyranetwork\Lyra\Sdk;

use Lyranetwork\Lyra\Service\ConfigService;
use Lyranetwork\Lyra\Sdk\Form\Api as LyraApi;
use Lyranetwork\Lyra\Sdk\Rest\Api as LyraRest;
use Lyranetwork\Lyra\Service\LocaleCodeService;

use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;

use Psr\Log\LoggerInterface;

class RestData
{
    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var LocaleCodeService
     */
    private $localeCodeService;

    /**
     * @var string
     */
    private $shopwareVersion;

    public function __construct(
        ConfigService $configService,
        LoggerInterface $logger,
        LocaleCodeService $localeCodeService,
        string $shopwareVersion
    ) {
        $this->configService = $configService;
        $this->logger = $logger;
        $this->localeCodeService = $localeCodeService;
        $this->shopwareVersion = $shopwareVersion;
    }

    public function getPrivateKey()
    {
        $ctxMode = $this->configService->get('ctx_mode');
        $field = ($ctxMode == 'TEST') ? 'private_test_key' : 'private_prod_key';

        return $this->configService->get($field);
    }

    public function getPublicKey()
    {
        $ctxMode = $this->configService->get('ctx_mode');
        $field = ($ctxMode == 'TEST') ? 'public_test_key' : 'public_prod_key';

        return $this->configService->get($field);
    }

    public function getReturnKey()
    {
        $ctxMode = $this->configService->get('ctx_mode');
        $field = ($ctxMode == 'TEST') ? 'hmac_test_key' : 'hmac_prod_key';

        return $this->configService->get($field);
    }

    public function convertRestResult($answer, $isTransaction = false)
    {
        if (! is_array($answer) || empty($answer)) {
            return [];
        }

        if ($isTransaction) {
            $transaction = $answer;
        } else {
            $transactions = $this->getProperty($answer, 'transactions');
            if (! is_array($transactions) || empty($transactions)) {
                return [];
            }

            $transaction = $transactions[0];
        }

        $response = [];

        $response['vads_result'] = $this->getProperty($transaction, 'errorCode') ? $this->getProperty($transaction, 'errorCode') : '00';
        $response['vads_extra_result'] = $this->getProperty($transaction, 'detailedErrorCode');

        if ($errorMessage = $this->getErrorMessage($transaction)) {
            $response['vads_error_message'] = $errorMessage;
        }

        $response['vads_trans_status'] = $this->getProperty($transaction, 'detailedStatus');
        $response['vads_trans_uuid'] = $this->getProperty($transaction, 'uuid');
        $response['vads_operation_type'] = $this->getProperty($transaction, 'operationType');
        $response['vads_effective_creation_date'] = $this->getProperty($transaction, 'creationDate');
        $response['vads_payment_config'] = 'SINGLE'; // Only single payments are possible via REST API at this time.

        if ($customer = $this->getProperty($answer, 'customer')) {
            $response['vads_cust_email'] = $this->getProperty($customer, 'email');

            if ($billingDetails = $this->getProperty($customer, 'billingDetails')) {
                $response['vads_language'] = $this->getProperty($billingDetails, 'language');
            }
        }

        $response['vads_amount'] = $this->getProperty($transaction, 'amount');
        $response['vads_currency'] = LyraApi::getCurrencyNumCode($this->getProperty($transaction, 'currency'));

        if ($paymentToken = $this->getProperty($transaction, 'paymentMethodToken')) {
            $response['vads_identifier'] = $paymentToken;
            $response['vads_identifier_status'] = 'CREATED';
        }

        if ($orderDetails = $this->getProperty($answer, 'orderDetails')) {
            $response['vads_order_id'] = $this->getProperty($orderDetails, 'orderId');
        }

        if (($metadata = $this->getProperty($transaction, 'metadata')) && is_array($metadata)) {
            foreach ($metadata as $key => $value) {
                $response['vads_ext_info_' . $key] = $value;
            }
        }

        if ($transactionDetails = $this->getProperty($transaction, 'transactionDetails')) {
            $response['vads_sequence_number'] = $this->getProperty($transactionDetails, 'sequenceNumber');

            // Workarround to adapt to REST API behavior.
            $effectiveAmount = $this->getProperty($transactionDetails, 'effectiveAmount');
            $effectiveCurrency = LyraApi::getCurrencyNumCode($this->getProperty($transactionDetails, 'effectiveCurrency'));

            if ($effectiveAmount && $effectiveCurrency) {
                // Invert only if there is currency conversion.
                if ($effectiveCurrency !== $response['vads_currency']) {
                    $response['vads_effective_amount'] = $response['vads_amount'];
                    $response['vads_effective_currency'] = $response['vads_currency'];
                    $response['vads_amount'] = $effectiveAmount;
                    $response['vads_currency'] = $effectiveCurrency;
                } else {
                    $response['vads_effective_amount'] = $effectiveAmount;
                    $response['vads_effective_currency'] = $effectiveCurrency;
                }
            }

            $response['vads_warranty_result'] = $this->getProperty($transactionDetails, 'liabilityShift');

            if ($cardDetails = $this->getProperty($transactionDetails, 'cardDetails')) {
                $response['vads_trans_id'] = $this->getProperty($cardDetails, 'legacyTransId'); // Deprecated.
                $response['vads_presentation_date'] = $this->getProperty($cardDetails, 'expectedCaptureDate');

                $response['vads_card_brand'] = $this->getProperty($cardDetails, 'effectiveBrand');
                $response['vads_card_number'] = $this->getProperty($cardDetails, 'pan');
                $response['vads_expiry_month'] = $this->getProperty($cardDetails, 'expiryMonth');
                $response['vads_expiry_year'] = $this->getProperty($cardDetails, 'expiryYear');

                $response['vads_payment_option_code'] = $this->getProperty($cardDetails, 'installmentNumber');


                if ($authorizationResponse = $this->getProperty($cardDetails, 'authorizationResponse')) {
                    $response['vads_auth_result'] = $this->getProperty($authorizationResponse, 'authorizationResult');
                    $response['vads_authorized_amount'] = $this->getProperty($authorizationResponse, 'amount');
                }

                if (($authenticationResponse = self::getProperty($cardDetails, 'authenticationResponse'))
                    && ($value = self::getProperty($authenticationResponse, 'value'))) {
                    $response['vads_threeds_status'] = self::getProperty($value, 'status');
                    $response['vads_threeds_auth_type'] = self::getProperty($value, 'authenticationType');
                    if ($authenticationValue = self::getProperty($value, 'authenticationValue')) {
                        $response['vads_threeds_cavv'] = self::getProperty($authenticationValue, 'value');
                    }
                } elseif (($threeDSResponse = self::getProperty($cardDetails, 'threeDSResponse'))
                    && ($authenticationResultData = self::getProperty($threeDSResponse, 'authenticationResultData'))) {
                    $response['vads_threeds_cavv'] = self::getProperty($authenticationResultData, 'cavv');
                    $response['vads_threeds_status'] = self::getProperty($authenticationResultData, 'status');
                    $response['vads_threeds_auth_type'] = self::getProperty($authenticationResultData, 'threeds_auth_type');
                }
            }

            if ($fraudManagement = $this->getProperty($transactionDetails, 'fraudManagement')) {
                if ($riskControl = $this->getProperty($fraudManagement, 'riskControl')) {
                    $response['vads_risk_control'] = '';

                    foreach ($riskControl as $value) {
                        if (! isset($value['name']) || ! isset($value['result'])) {
                            continue;
                        }

                        $response['vads_risk_control'] .= "{$value['name']}={$value['result']};";
                    }
                }

                if ($riskAssessments = $this->getProperty($fraudManagement, 'riskAssessments')) {
                    $response['vads_risk_assessment_result'] = $this->getProperty($riskAssessments, 'results');
                }
            }
        }

        return $response;
    }

    private function getProperty($restResult, $key)
    {
        if (isset($restResult[$key])) {
            return $restResult[$key];
        }

        return null;
    }

    private function getErrorMessage($transaction)
    {
        $code = $this->getProperty($transaction, 'errorCode');
        if ($code) {
            return ucfirst($this->getProperty($transaction, 'errorMessage')) . ' (' . $code . ').';
        }

        return null;
    }

    public function checkResponseHash($data, $key): bool
    {
        $supportedSignAlgos = array('sha256_hmac');

        // Check if the hash algorithm is supported.
        if (! in_array($data['hashAlgorithm'], $supportedSignAlgos)) {
            $this->logger->error('Hash algorithm is not supported: ' . $data['hashAlgorithm']);
            return false;
        }

        // On some servers, / can be escaped.
        $krAnswer = str_replace('\/', '/', ($data['rawClientAnswer'] ?: json_encode($data['clientAnswer'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));

        $hash = hash_hmac('sha256', $krAnswer, $key);

        // Return true if calculated hash and sent hash are the same.
        return ($hash === $data['hash']);
    }

    public function getToken(CheckoutConfirmPageLoadedEvent $event)
    {
        $cart = $event->getPage()->getCart();

        if (! $cart || ! $cart->getToken()) {
            $this->logger->error('Cannot create a form token. Empty cart passed');
            return false;
        }

        if ($cart->getPrice()->getTotalPrice() <= 0) {
            $this->logger->error('Cannot create a form token. Invalid amount passed.');
            return false;
        }

        $params = $this->getRestApiFormTokenData($event);

        $this->logger->info("Creating form token for cart #{$cart->getToken()} with parameters: {$params}");

        try {
            // Perform our request.
            $client = new LyraRest(
                $this->configService->get('rest_server_url'),
                $this->configService->get('site_id'),
                $this->getPrivateKey()
            );

            $response = $client->post('V4/Charge/CreatePayment', $params);

            if ($response['status'] !== 'SUCCESS') {
                $msg = "Error while creating payment form token for quote #{$cart->getToken()}: " . $response['answer']['errorMessage'] . ' (' . $response['answer']['errorCode'] . ').';

                if (! empty($response['answer']['detailedErrorMessage'])) {
                    $msg .= ' Detailed message: ' . $response['answer']['detailedErrorMessage'] .' (' . $response['answer']['detailedErrorCode'] . ').';
                }

                $this->logger->error($msg);
                return false;
            } else {
                $this->logger->info("Form token created successfully for quote #{$cart->getToken()}.");

                return $response['answer']['formToken'];
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    private function getRestApiFormTokenData($event)
    {
        $cart = $event->getPage()->getCart();

        $currencyIso = $event->getSalesChannelContext()->getSalesChannel()->getCurrency()->getIsoCode(); // Current shop currency.
        $currency = LyraApi::findCurrencyByAlphaCode($currencyIso);

        $amount = $cart->getPrice()->getTotalPrice();

        $customer = $event->getSalesChannelContext()->getCustomer();

        if (! $currency) {
            $this->logger->error('Cannot create a form token. Unsupported currency passed.');
            return false;
        }

        // Activate 3DS?
        $strongAuth = 'AUTO';
        $threedsMinAmount = $this->configService->get('3ds_min_amount');
        if ($threedsMinAmount && $amount < $threedsMinAmount) {
            $strongAuth = 'DISABLED';
        }

        $billingAddress = $customer->getDefaultBillingAddress();

        $salesChannelId = $event->getSalesChannelContext()->getSalesChannelId();
        $lang = $this->localeCodeService->getLocaleCodeFromContext( $event->getSalesChannelContext()->getContext());
        $lyraLang = LyraApi::isSupportedLanguage($lang) ? $lang : $this->configService->get('language', $salesChannelId);

        $version = Tools::getDefault('CMS_IDENTIFIER') . '_v' . Tools::getDefault('PLUGIN_VERSION');

        $data = [
            'orderId' => 'order' . LyraApi::generateTransId(),
            'customer' => [
                'email' => $customer->getEmail(),
                'reference' => $customer->getCustomerNumber(),
                'billingDetails' => [
                    'language' => $lyraLang,
                    'title' => $billingAddress->getTitle(),
                    'firstName' => $billingAddress->getFirstname(),
                    'lastName' => $billingAddress->getLastname(),
                    'address' => $billingAddress->getStreet(),
                    'zipCode' => $billingAddress->getZipCode(),
                    'city' => $billingAddress->getCity(),
                    'phoneNumber' => $billingAddress->getPhoneNumber(),
                    'cellPhoneNumber' => $billingAddress->getPhoneNumber(),
                    'country' => $billingAddress->getCountry()->getIso()
                ]
            ],
            'transactionOptions' => [
                'cardOptions' => [
                    'paymentSource' => 'EC'
                ]
            ],
            'contrib' =>  $version . '/' . $this->shopwareVersion . '/' . LyraApi::shortPhpVersion(),
            'strongAuthentication' => $strongAuth,
            'currency' => $currency->getAlpha3(),
            'amount' => $currency->convertAmountToInteger($amount),
            'metadata' => array(
                'sales_channel_id' => $salesChannelId
            )
        ];

        // In case of Smartform, only payment means supporting capture delay will be shown.
        if (is_numeric($this->configService->get('capture_delay'))) {
            $data['transactionOptions']['cardOptions']['capture_delay'] = $this->configService->get('capture_delay');
        }

        $validationMode = $this->configService->get('validation_mode');
        if (! is_null($validationMode)) {
            $data['transactionOptions']['cardOptions']['manualValidation'] = ($validationMode === '1') ? 'YES' : 'NO';
        }

        // Set shipping info.
        if (($shippingAddress = $customer->getDefaultShippingAddress()) && is_object($shippingAddress)) {
            $data['customer']['shippingDetails'] = array(
                'firstName' => $shippingAddress->getFirstName(),
                'lastName' => $shippingAddress->getLastName(),
                'address' => $shippingAddress->getStreet(),
                'zipCode' => $shippingAddress->getZipcode(),
                'city' => $shippingAddress->getCity(),
                'phoneNumber' => $shippingAddress->getPhoneNumber(),
                'country' => $shippingAddress->getCountry()->getIso()
            );
        }

        if (! empty($customer->getDefaultBillingAddress()->getCountryState())) {
            $data['customer']['billingDetails']['state'] = $billingAddress->getCountryState()->getShortCode();
        }

        if (! empty($customer->getDefaultShippingAddress()->getCountryState())) {
            $data['customer']['shippingDetails']['state'] = $shippingAddress->getCountryState()->getShortCode();
        }

        if ($this->isSmartform()) {
            // Filter payment means when creating payment token.
            $data['paymentMethods'] = $this->getPaymentMeansForSmartform($amount);
        }

        return json_encode($data);
    }

    private function getPaymentMeansForSmartform($amount)
    {
        $paymentCards = $this->configService->get('payment_cards');

        // Get standard payments means.
        if ($paymentCards != "") {
            $stdPaymentMeans = explode(';', $paymentCards);
        } else {
            return array();
        }

        // Merge standard and other payment means.
        return $stdPaymentMeans;
    }

    public function isSmartform(): bool
    {
        $cardDataMode = $this->configService->get('card_data_mode');
        return in_array($cardDataMode, Tools::$smartformModes);
    }
}