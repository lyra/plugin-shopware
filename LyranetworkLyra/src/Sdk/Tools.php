<?php
/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect plugin for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

declare(strict_types=1);
namespace Lyranetwork\Lyra\Sdk;

use Symfony\Component\HttpFoundation\Request;

use Lyranetwork\Lyra\Sdk\Form\Response as LyraResponse;
use Lyranetwork\Lyra\Sdk\Form\Api as LyraApi;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

class Tools
{
    private static $GATEWAY_CODE = 'Lyra';
    private static $GATEWAY_NAME = 'Lyra Collect';
    private static $BACKOFFICE_NAME = 'Lyra Expert';
    private static $GATEWAY_URL = 'https://secure.lyra.com/vads-payment/';
    private static $SITE_ID = '12345678';
    private static $KEY_TEST = '1111111111111111';
    private static $KEY_PROD = '2222222222222222';
    private static $CTX_MODE = 'TEST';
    private static $SIGN_ALGO = 'SHA-256';
    private static $LANGUAGE = 'en';

    private static $CMS_IDENTIFIER = 'Shopware_6.5.x';
    private static $SUPPORT_EMAIL = 'support-ecommerce@lyra-collect.com';
    private static $PLUGIN_VERSION = '4.1.0';
    private static $GATEWAY_VERSION = 'V2';
    private static $REST_URL = 'https://api.lyra.com/api-payment/';
    private static $STATIC_URL = 'https://static.lyra.com/static/';

    public static $pluginFeatures = [
        'qualif' => false,
        'prodfaq' => false,
        'shatwo' => true,
        'smartform' => true
    ];

    public static $smartformModes = [
        'MODE_SMARTFORM',
        'MODE_SMARTFORM_EXT_WITH_LOGOS',
        'MODE_SMARTFORM_EXT_WITHOUT_LOGOS'
    ];

    public static function getDefault($name)
    {
        if (! is_string($name)) {
            return '';
        }

        if (! isset(self::$$name)) {
            return '';
        }

        return self::$$name;
    }

    public static function getDocPattern()
    {
        $version = self::getDefault('PLUGIN_VERSION');
        $minor = substr($version, 0, strrpos($version, '.'));

        return self::getDefault('GATEWAY_CODE') . '_' . self::getDefault('CMS_IDENTIFIER') . '_v' . $minor . '*.pdf';
    }

    public static function checkRestIpnValidity($request): bool
    {
        return $request->get('kr-hash') !== null && $request->get('kr-hash-algorithm') !== null && $request->get('kr-answer') !== null;
    }

    public static function getNewOrderPaymentStatus(LyraResponse $lyraResponse, ?string $successStatus)
    {
        $status = $lyraResponse->get('trans_status');

        if (in_array($status, LyraApi::getSuccessStatuses(), true)) {
            return $successStatus;
        } elseif ($lyraResponse->isPendingPayment()) {
            return OrderTransactionStates::STATE_OPEN; // Open.
        } elseif ($lyraResponse->isCancelledPayment()) {
            return OrderTransactionStates::STATE_CANCELLED; // Cancelled.
        } else {
            return OrderTransactionStates::STATE_FAILED; // Failed.
        }
    }

    public static function getNewOrderTransition(LyraResponse $lyraResponse)
    {
        $status = $lyraResponse->get('trans_status');

        if ($lyraResponse->isPendingPayment()) {
            return 'pending';
        } elseif ($lyraResponse->isAcceptedPayment()) {
            return StateMachineTransitionActions::ACTION_PROCESS;
        } elseif ($lyraResponse->isCancelledPayment()) {
            return StateMachineTransitionActions::ACTION_CANCEL;
        } else {
            // Case of failed payments.
            return StateMachineTransitionActions::ACTION_REOPEN;
        }
    }
}