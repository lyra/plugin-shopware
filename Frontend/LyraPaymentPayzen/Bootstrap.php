<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for ShopWare. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   http://www.gnu.org/licenses/agpl.html GNU Affero General Public License (AGPL v3)
 */

use Shopware\Models\Order\Repository;

require_once 'Components/PayzenApi.php';
require_once 'Components/PayzenTools.php';

class Shopware_Plugins_Frontend_LyraPaymentPayzen_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{

    /**
     * Installs the plugin:
     *  - create and save the payment row,
     *  - create the payment table,
     *  - create admin form translations,
     *  - create and subscribe the events and hooks.
     *
     * @return array[string][mixed]
     */
    public function install()
    {
        $this->createMyPayment();
        $this->createMyForm();
        $this->createMyTranslations();
        $this->createMyEvents();

        $this->fixOrderMail();
        $this->fixBackendOrderDetails();

        // Enable our plugin just after installation.
        $repo = Shopware()->Models()->getRepository('Shopware\Models\Plugin\Plugin');
        $plugin = $repo->findOneBy(array('name' => 'LyraPaymentPayzen'));
        $plugin->setActive(true);
        Shopware()->Models()->flush($plugin);

        return array('success' => true, 'invalidateCache' => array('config', 'backend', 'frontend'));
    }

    private function fixOrderMail()
    {
        $sql = "SELECT `content` FROM `s_core_config_mails` WHERE `name` = 'sORDER'";
        $content = Shopware()->Db()->fetchOne($sql);

        // Remove img tags from additional description if any.
        $ad = "\$additional.payment.additionaldescription|regex_replace:'#^<img[^>]+/>#':''";
        $content = preg_replace("@\\\$additional\.payment\.additionaldescription(\|regex_replace\:\'#\^<img\[\^>\]\+/>#\'\:\'\')*@", $ad, $content);

        // Make sure that the "additionadescription" field is evaluated by Smarty.
        $sql = "UPDATE `s_core_config_mails` SET `content` = ? WHERE `name` = 'sORDER'";
        Shopware()->Db()->query($sql, array($content));
    }

    private function fixBackendOrderDetails()
    {
        if (version_compare(Shopware()->Config()->version, '5.2.0', '<')) {
            return;
        }

        $sql = "SELECT COUNT(*) FROM `s_attribute_configuration` WHERE `table_name` = 's_order_attributes' AND `column_name` IN ('attribute1', 'attribute2', 'attribute3', 'attribute4', 'attribute5', 'attribute6')";
        $count = Shopware()->Db()->fetchOne($sql);

        if (! $count) {
            $sql = "INSERT INTO `s_attribute_configuration` (`table_name`, `column_name`, `column_type`, `position`, `translatable`, `display_in_backend`, `custom`, `label`)
                         VALUES ('s_order_attributes', 'attribute1', 'string', 1, 0, 1, 0, 'Message'),
                                ('s_order_attributes', 'attribute2', 'string', 2, 0, 1, 0, 'Transaction type'),
                                ('s_order_attributes', 'attribute3', 'string', 3, 0, 1, 0, 'Transaction ID'),
                                ('s_order_attributes', 'attribute4', 'string', 4, 0, 1, 0, 'Means of payment'),
                                ('s_order_attributes', 'attribute5', 'string', 5, 0, 1, 0, 'Card number'),
                                ('s_order_attributes', 'attribute6', 'string', 6, 0, 1, 0, 'Expiration date')";
            Shopware()->Db()->query($sql);
        }
    }

    /**
     * Uninstalls the plugin.
     *
     * @return bool
     */
    public function uninstall()
    {
        $payment = $this->Payment();
        if ($payment && $payment->getId()) {
            $sql = "SELECT COUNT(*) FROM `s_order` WHERE `paymentID` = ?";
            $count = Shopware()->Db()->fetchOne($sql, array($payment->getId()));

            if (! $count) {
                // No orders associated with our payment method, let's delete it.
                $sql = "DELETE FROM `s_core_paymentmeans` WHERE `name` = 'payzen'";
                Shopware()->Db()->query($sql);
            }
        }

        // Delete payment snippets.
        $sql = "DELETE FROM `s_core_snippets` WHERE `name` LIKE 'payzen/%'";
        Shopware()->Db()->query($sql);

        return array('success' => true, 'invalidateCache' => array('config', 'backend', 'frontend'));
    }

    /**
     * Updates the plugin.
     *
     * @param string $version
     * @return bool
     */
    public function update($version)
    {
        $this->createMyForm();
        $this->createMyTranslations();
        $this->createMyEvents();

        $this->fixOrderMail();
        $this->fixBackendOrderDetails();

        return array('success' => true, 'invalidateCache' => array('config', 'backend', 'frontend'));
    }

    /**
     * Activate the plugin. Set the active flag in the payment row.
     *
     * @return bool
     */
    public function enable()
    {
        $payment = $this->Payment();
        if ($payment !== null) {
            $payment->setActive(true);
            Shopware()->Models()->flush($payment);
        }

        return array('success' => true, 'invalidateCache' => array('config', 'backend', 'frontend'));
    }

    /**
     * Disable plugin method and set the active flag in the payment row.
     *
     * @return bool
     */
    public function disable()
    {
        $payment = $this->Payment();
        if ($payment !== null) {
            $payment->setActive(false);
            Shopware()->Models()->flush($payment);
        }

        return array('success' => true, 'invalidateCache' => array('config', 'backend', 'frontend'));
    }

    /**
     * Returns misc info about plugin.
     *
     * @return array
     */
    public function getInfo()
    {
        $logo = base64_encode(file_get_contents(dirname(__FILE__) . '/logo.png'));

        $rootDir = str_replace('\\', '/', Shopware()->DocPath());

        // Documentation path.
        $absolutePath = str_replace('\\', '/', dirname(__FILE__)) . '/installation_doc/';
        $relativePath = str_replace($rootDir, '', $absolutePath);

        // Get documentation links.
        $docs = '';
        $filenames = glob(str_replace('\\', '/', dirname(__FILE__)) . '/installation_doc/' . PayzenTools::getDocPattern());

        $languages = array(
            'fr' => 'Français',
            'en' => 'English',
            'es' => 'Español',
            'de' => 'Deutsch'
            // Complete when other languages are managed.
        );

        foreach ($filenames as $filename) {
            $baseFilename = basename($filename, '.pdf');
            $lang = substr($baseFilename, -2); // Extract language code.

            $docs .= '<a style="margin-left: 10px; text-decoration: none; text-transform: uppercase;" href="' .
                Shopware()->Front()->Request()->getBasePath() . '/' . $relativePath . $baseFilename . '.pdf" target="_blank">' .
                $languages[$lang] . '</a>';
        }

        return array(
            'version' => $this->getVersion(),
            'label' => $this->getLabel(),
            'description' => '<img src="data:image/png;base64,' . $logo . '" /> ' .
                '<p>This plugin enables you to setup the PayZen payment system on your ShopWare website.</p>' .
                'Click to view the module configuration documentation: ' . $docs,
            'author' => 'Lyra Network',
            'copyright' => 'Copyright © 2015-2019, Lyra Network',
            'license' => 'AGPLv3',
            'support' => PayzenTools::getDefault('SUPPORT_EMAIL'),
            'link' => 'https://www.lyra.com/'
        );
    }

    /**
     * Returns the version of plugin as string.
     *
     * @return string
     */
    public function getVersion()
    {
        return PayzenTools::getDefault('PLUGIN_VERSION');
    }

    /**
     * Returns the label of plugin as string.
     *
     * @return string
     */
    public function getLabel()
    {
        return 'PayZen Payment';
    }

    /**
     * Returns capabilities of this module.
     *
     * @return array[string][bool]
     */
    public function getCapabilities()
    {
        return array(
            'install' => true,
            'update' => true,
            'enable' => true
        );
    }

    /**
     * Get the payment instance information.
     *
     * @return object
     */
    public function Payment()
    {
        return $this->Payments()->findOneBy(array('name' => 'payzen'));
    }

    /**
     * Create and save the payment row.
     */
    protected function createMyPayment()
    {
        $logo = 'payzen_cards.png';
        $pluginImgPath = dirname(__FILE__) . '/Views/frontend/_resources/images/';
        $mediaPath = $this->Application()->DocPath() . 'media/image/';
        if (! is_file($mediaPath . $logo)) {
            copy($pluginImgPath . $logo, $mediaPath . $logo);
            $sql = "INSERT INTO `s_media` (`albumID`, `name`, `description`, `path`, `type`, `extension`, `file_size`, `created`)
                    VALUES (-3, 'payzen_cards', '', 'media/image/$logo', 'IMAGE', 'png', " . filesize($mediaPath . $logo) . ", CURDATE())";
            Shopware()->Db()->query($sql);
        }

        $this->createPayment(array(
            'name' => 'payzen',
            'description' => 'PayZen',
            'additionalDescription' => '<img alt="PayZen" src="{link file="media/image/' . $logo . '" fullPath}" />' .'<p>Pay by credit card<p/>',
            'action' => 'payment_payzen',
            'active' => 1,
            'position' => 1
        ));
    }

    /**
     * Create and save the payment config form.
     */
    protected function createMyForm()
    {
        $form = $this->Form();

        if (version_compare(Shopware()->Config()->version, '4.3.0', '>=')) {
            $ctxModes = array(
                array('PRODUCTION', array('de_DE' => 'PRODUKTION', 'en_GB' => 'PRODUCTION', 'fr_FR' => 'PRODUCTION', 'es_ES' => 'PRODUCTION')),
                array('TEST', array('de_DE' => 'TEST', 'en_GB' => 'TEST', 'fr_FR' => 'TEST', 'es_ES' => 'TEST'))
            );

            $snippets = array(
                'de_DE' => array(
                    'de' => 'Deutsch', 'en' => 'Englisch', 'es' => 'Spanisch', 'fr' => 'Französisch', 'it' => 'Italienisch',
                    'jp' => 'Japanisch', 'nl' => 'Niederländisch', 'pl' => 'Polnisch', 'pt' => 'Portugiesisch',
                    'ru' => 'Russisch', 'sv' => 'Schwedisch', 'tr' => 'Türkisch', 'zn' => 'Chinesisch'
                ),
                'en_GB' => array(
                    'de' => 'German', 'en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'it' => 'Italian',
                    'jp' => 'Japanese', 'nl' => 'Dutch', 'pl' => 'Polish', 'pt' => 'Portuguese', 'ru' => 'Russian',
                    'sv' => 'Swedish', 'tr' => 'Turkish', 'zn' => 'Chinese'
                ),
                'fr_FR' => array(
                    'de' => 'Allemand', 'en' => 'Anglais', 'es' => 'Espagnol', 'fr' => 'Français', 'it' => 'Italien',
                    'jp' => 'Japonais', 'nl' => 'Néerlandais', 'pl' => 'Polonais', 'pt' => 'Portugais', 'ru' => 'Russe',
                    'sv' => 'Suédois', 'tr' => 'Turc', 'zn' => 'Chinois'
                ),
                'es_ES' => array(
                    'de' => 'Alemán', 'en' => 'Inglés', 'es' => 'Español', 'fr' => 'Francés', 'it' => 'Italiano',
                    'jp' => 'Japonés', 'nl' => 'Holandés', 'pl' => 'Polaco', 'pt' => 'Portugués', 'ru' => 'Ruso',
                    'sv' => 'Sueco', 'tr' => 'Turco', 'zn' => 'Chino'
                )
            );

            $languages = array();
            foreach (PayzenApi::getSupportedLanguages() as $key => $language) {
                $languages[] = array($key, array(
                    'de_DE' => key_exists($key, $snippets['de_DE']) ? $snippets['de_DE'][$key] : $language,
                    'en_GB' => key_exists($key, $snippets['en_GB']) ? $snippets['en_GB'][$key] : $language,
                    'fr_FR' => key_exists($key, $snippets['fr_FR']) ? $snippets['fr_FR'][$key] : $language,
                    'es_ES' => key_exists($key, $snippets['es_ES']) ? $snippets['es_ES'][$key] : $language
                ));
            }

            $validationModes = array(
                array('', array('de_DE' => 'Einstellungen des PayZen Back Office', 'en_GB' => 'PayZen Back Office configuration', 'fr_FR' => 'Configuration Back Office PayZen', 'es_ES' => 'Configuración de Back Office PayZen')),
                array('0', array('de_DE' => 'Automatisch', 'en_GB' =>'Automatic', 'fr_FR' => 'Automatique', 'es_ES' => 'Automático')),
                array('1', array('de_DE' => 'Manuell', 'en_GB' =>'Manual', 'fr_FR' => 'Manuelle', 'es_ES' =>'Manual'))
            );

            $cards = array();
            foreach (PayzenApi::getSupportedCardTypes() as $key => $card) {
                $cards[] = array($key, $card);
            }

            $enableOptions = array(
                array('False', array('de_DE' => 'Deaktiviert', 'en_GB' => 'Disabled', 'fr_FR' => 'Désactivé', 'es_ES' => 'Deshabilitado')),
                array('True', array('de_DE' => 'Aktiviert', 'en_GB' => 'Enabled', 'fr_FR' => 'Activé', 'es_ES' => 'Habilitado'))
            );

            $returnModes = array(array('GET', 'GET'), array('POST', 'POST'));

            $paymentStatuses = $this->getPaymentStatuses();

            $signAlgos = array(array('SHA-1', 'SHA-1'), array('SHA-256', 'HMAC-SHA-256'));
        } else {
            $ctxModes = array('PRODUCTION', 'TEST');

            $languages = array();
            foreach (PayzenApi::getSupportedLanguages() as $key => $language) {
                $languages[] = array('value' => $key, 'label' => $language);
            }

            $languages = array('fields' => array('value', 'label'), 'data' => $languages);

            $validationModes = array(
                'fields' => array('value', 'label'),
                'data' => array(
                    array('value' => '', 'label' => 'PayZen Back Office configuration'),
                    array('value' => '0', 'label' => 'Automatic'),
                    array('value' => '1', 'label' => 'Manual')
                )
            );

            $cards = array();
            foreach (PayzenApi::getSupportedCardTypes() as $key => $card) {
                $cards[] = array('value' => $key, 'label' => $card);
            }

            $cards = array('fields' => array('value', 'label'), 'data' => $cards);

            $enableOptions = array(
                'fields' => array('value', 'label'),
                'data' => array(
                    array('value' => 'False', 'label' => 'Disabled'),
                    array('value' => 'True', 'label' => 'Enabled')
                )
            );

            $returnModes = array('GET', 'POST');

            $paymentStatuses = $this->getPaymentStatuses(true);

            $signAlgos = array(
                'fields' => array('value', 'label'),
                'data' => array(
                    array('value' => 'SHA-1', 'label' => 'SHA-1'),
                    array('value' => 'SHA-1', 'label' => 'HMAC-SHA-256')
                )
            );
        }

        $defaultLanguage = PayzenTools::getDefault('LANGUAGE');
        $defaultRedirectionMsg = array(
            'en' => 'Redirection to shop in a few seconds...',
            'fr' => 'Redirection vers la boutique dans quelques instants...',
            'de' => 'Weiterleitung zum Shop in Kürze...',
            'es' => 'Redirección a la tienda en unos momentos...'
        );

        // Module information.
        $form->setElement('text', 'payzen_developed_by', array(
            'label' => 'Developed by',
            'value' => 'https://www.lyra.com',
            'readOnly' => true,
            'fieldCls' => '',
            'focusCls' => '',
            'baseBodyCls' => '',
            'fieldStyle' => 'border: none; background: none;',
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('text', 'payzen_contact_email', array(
            'label' => 'Contact us',
            'value' => PayzenTools::getDefault('SUPPORT_EMAIL'),
            'readOnly' => true,
            'fieldCls' => '',
            'focusCls' => '',
            'baseBodyCls' => '',
            'fieldStyle' => 'border: none; background: none;',
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('text', 'payzen_module_version', array(
            'label' => 'Module version',
            'value' => PayzenTools::getDefault('PLUGIN_VERSION'),
            'readOnly' => true,
            'fieldCls' => '',
            'focusCls' => '',
            'baseBodyCls' => '',
            'fieldStyle' => 'border: none; background: none;',
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('text', 'payzen_gateway_version', array(
            'label' => 'Gateway version',
            'value' => PayzenTools::getDefault('GATEWAY_VERSION'),
            'readOnly' => true,
            'fieldCls' => '',
            'focusCls' => '',
            'baseBodyCls' => '',
            'fieldStyle' => 'border: none; background: none;',
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        $form->setElement('text', 'payzen_gateway_access', array(
            'label' => 'PAYMENT GATEWAY ACCESS',
            'value' => '',
            'readOnly' => true,
            'baseBodyCls' => '',
            'fieldStyle' => 'display: none;',
            'labelStyle' => 'margin-top: 15px; margin-bottom: 15px;',
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        // Payment access.
        $form->setElement('text', 'payzen_site_id', array(
            'label' => 'Store identifier',
            'description' => 'The identifier provided by PayZen.',
            'value' => PayzenTools::getDefault('SITE_ID'),
            'required' => true,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        if(!PayzenTools::$pluginFeatures['qualif']) {
            $form->setElement('text', 'payzen_key_test', array(
                'label' => 'Key in test mode',
                'description' => 'Key provided by PayZen for test mode (available in PayZen Back Office).',
                'value' => PayzenTools::getDefault('KEY_TEST'),
                'required' => true,
                'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
            ));
        }

        $form->setElement('text', 'payzen_key_prod', array(
            'label' => 'Key in production mode',
            'description' => 'Key provided by your bank (available in PayZen Back Office after enabling production mode).',
            'value' => PayzenTools::getDefault('KEY_PROD'),
            'required' => true,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('select', 'payzen_ctx_mode', array(
            'label' => 'Mode',
            'description' => 'The context mode of this module.',
            'value' => PayzenTools::getDefault('CTX_MODE'),
            'readOnly' => PayzenTools::$pluginFeatures['qualif'],
            'store' => $ctxModes,
            'editable' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        $sha2 = PayzenTools::$pluginFeatures['shatwo'];

        $form->setElement('select', 'payzen_sign_algo', array(
            'label' => 'Signature algorithm',
            'description' => 'Algorithm used to compute the payment form signature. Selected algorithm must be the same as one configured in the PayZen Back Office.' . (! $sha2 ? '<br /><b>The HMAC-SHA-256 algorithm should not be activated if it is not yet available in the PayZen Back Office, the feature will be available soon.</b>' : ''),
            'value' => PayzenTools::getDefault('SIGN_ALGO'),
            'store' => $signAlgos,
            'editable' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        // Notification URL.
        $shop = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop')->getActiveDefault();

        $form->setElement('text', 'payzen_check_url', array(
            'label' => 'Instant Payment Notification URL',
            'description' => 'URL to copy into your PayZen Back Office > Settings > Notification rules.',
            'value' => $this->getBaseUrl($shop) . '/payment_payzen/process',
            'readOnly' => true,
            'fieldCls' => '',
            'focusCls' => '',
            'fieldStyle' => 'border: none;',
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        $form->setElement('text', 'payzen_platform_url', array(
            'label' => 'Payment page URL',
            'description' => 'Link to the payment page.',
            'value' => PayzenTools::getDefault('GATEWAY_URL'),
            'required' => true,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        $form->setElement('text', 'payzen_payment_page', array(
            'label' => 'PAYMENT PAGE',
            'value' => '',
            'readOnly' => true,
            'baseBodyCls' => '',
            'fieldStyle' => 'display: none;',
            'labelStyle' => 'margin-top: 15px; margin-bottom: 15px;',
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        // Payment page.
        $form->setElement('select', 'payzen_language', array(
            'label' => 'Default language',
            'description' => 'Default language on the payment page.',
            'value' => $defaultLanguage,
            'store' => $languages,
            'displayField' => 'label',
            'valueField' => 'value',
            'editable' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('select', 'payzen_available_languages', array(
            'label' => 'Available languages',
            'description' => 'Languages available on the payment page. If you do not select any, all the supported languages will be available.',
            'value' => '',
            'store' => $languages,
            'displayField' => 'label',
            'valueField' => 'value',
            'multiSelect' => true,
            'editable' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('text', 'payzen_capture_delay', array(
            'label' => 'Capture delay',
            'description' => 'The number of days before the bank capture (adjustable in your PayZen Back Office).',
            'required' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('select', 'payzen_validation_mode', array(
            'label' => 'Validation mode',
            'description' => 'If manual is selected, you will have to confirm payments manually in your PayZen Back Office.',
            'value' => '',
            'store' => $validationModes,
            'displayField' => 'label',
            'valueField' => 'value',
            'editable' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('select', 'payzen_payment_cards', array(
            'label' => 'Card Types',
            'description' => 'The card type(s) that can be used for the payment. Select none to use gateway configuration.',
            'value' => '',
            'store' =>  $cards,
            'displayField' => 'label',
            'valueField' => 'value',
            'editable' => false,
            'multiSelect' => true,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        $form->setElement('text', 'payzen_selective_3ds', array(
            'label' => 'SELECTIVE 3DS',
            'value' => '',
            'readOnly' => true,
            'baseBodyCls' => '',
            'fieldStyle' => 'display: none;',
            'labelStyle' => 'margin-top: 15px; margin-bottom: 15px;',
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        // Selective 3DS.
        $form->setElement('number', 'payzen_3ds_min_amount', array(
            'label' => 'Disable 3DS',
            'description' => 'Amount below which 3DS will be disabled. Needs subscription to selective 3DS option. For more information, refer to the module documentation.',
            'required' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        $form->setElement('text', 'payzen_amount_restrictions', array(
            'label' => 'AMOUNT RESTRICTIONS',
            'value' => '',
            'readOnly' => true,
            'baseBodyCls' => '',
            'fieldStyle' => 'display: none;',
            'labelStyle' => 'margin-top: 15px; margin-bottom: 15px;',
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        // Amount restrictions.
        $form->setElement('number', 'payzen_min_amount', array(
            'label' => 'Minimum amount',
            'description' => 'Minimum amount to activate this payment method.',
            'required' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('number', 'payzen_max_amount', array(
            'label' => 'Maximum amount',
            'description' => 'Maximum amount to activate this payment method.',
            'required' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        $form->setElement('text', 'payzen_return_to_shop', array(
            'label' => 'RETURN TO SHOP',
            'value' => '',
            'readOnly' => true,
            'baseBodyCls' => '',
            'fieldStyle' => 'display: none;',
            'labelStyle' => 'margin-top: 15px; margin-bottom: 15px;',
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        // Return to shop.
        $form->setElement('select', 'payzen_redirect_enabled', array(
            'label' => 'Automatic redirection',
            'description' => 'If enabled, the buyer is automatically redirected to your site at the end of the payment.',
            'value' => 'False',
            'store' => $enableOptions,
            'displayField' => 'label',
            'valueField' => 'value',
            'editable' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('number', 'payzen_redirect_success_timeout', array(
            'label' => 'Redirection timeout on success',
            'description' => 'Time in seconds (0-300) before the buyer is automatically redirected to your website after a successful payment.',
            'value' => '5',
            'required' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('text', 'payzen_redirect_success_message', array(
            'label' => 'Redirection message on success',
            'description' => 'Message displayed on the payment page prior to redirection after a successful payment.',
            'value' => $defaultRedirectionMsg[$defaultLanguage],
            'required' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('number', 'payzen_redirect_error_timeout', array(
            'label' => 'Redirection timeout on failure',
            'description' => 'Time in seconds (0-300) before the buyer is automatically redirected to your website after a declined payment.',
            'value' => '5',
            'required' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('text', 'payzen_redirect_error_message', array(
            'label' => 'Redirection message on failure',
            'description' => 'Message displayed on the payment page prior to redirection after a declined payment.',
            'value' => $defaultRedirectionMsg[$defaultLanguage],
            'required' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('select', 'payzen_return_mode', array(
            'label' => 'Return mode',
            'description' => 'Method that will be used for transmitting the payment result from the payment page to your shop.',
            'value' => 'GET',
            'store' => $returnModes,
            'editable' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('select', 'payzen_payment_status_on_success', array(
            'label' => 'Order payment status',
            'description' => 'Defines the payment status of orders paid with this payment mode.',
            'value' => 12,
            'store' => $paymentStatuses,
            'editable' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
    }

    private function getPaymentStatuses($oldVersion = false)
    {
        $repository = Shopware()->Models()->getRepository('\Shopware\Models\Order\Order');
        $paymentStatuses = $repository->getPaymentStatusQuery()->getArrayResult();

        $snippetNamespace = $this->Application()->Snippets()->getNamespace('backend/static/payment_status');
        $paymentStatuses = array_map(function($status) use($snippetNamespace) {
            $status['description'] = $snippetNamespace->get($status['name'], $status['name'], true);

            return $status;
        }, $paymentStatuses);

        $result = array();
        if ($oldVersion) {
            $result['fields'] = array('value', 'label');
            $result['data'] = array();
        }

        foreach ($paymentStatuses as $paymentStatus) {
            if (! $paymentStatus['description']) {
                continue;
            }

            if ($oldVersion) {
                $result['data'][] = array('value' => $paymentStatus['id'], 'label' => $paymentStatus['description']);
            } else {
                $result[] = array($paymentStatus['id'], $paymentStatus['description']);
            }
        }

        return $result;
    }

    private function getBaseUrl($shop)
    {
        $secureHost = method_exists($shop, 'getSecureHost') ? $shop->getSecureHost() : $shop->getHost();
        $secureBasePath = method_exists($shop, 'getSecureBasePath') ? $shop->getSecureBasePath() : $shop->getBasePath();

        return $shop->getSecure() ?
            'https://' . $secureHost . $secureBasePath :
            'http://' . $shop->getHost() . $shop->getBasePath();
    }

    /**
     * Create module translations
     */
    protected function createMyTranslations()
    {
        $form = $this->Form();

        $sha2 = PayzenTools::$pluginFeatures['shatwo'];

        $translations = array(
            'de_DE' => array(
                'payzen_developed_by' => array('Entwickelt von', null),
                'payzen_contact_email' => array('E-Mail-Adresse', null),
                'payzen_module_version' => array('Modulversion', null),
                'payzen_gateway_version' => array('Kompatibel mit Zahlungsschnittstelle', null),
                'payzen_gateway_access' => array('ZUGANG ZAHLUNGSSCHNITTSTELLE', null),
                'payzen_site_id' => array('Shop ID', 'Die Kennung von PayZen bereitgestellt.'),
                'payzen_key_test' => array('Schlüssel im Testbetrieb', 'Schlüssel, das von Ihrer Bank zu Testzwecken bereitgestellt wird (im PayZen Back Office verfügbar).'),
                'payzen_key_prod' => array('Schlüssel im Produktivbetrieb', 'Schlüssel, das von PayZen zu Testzwecken bereitgestellt wird (im PayZen Back Office verfügbar).'),
                'payzen_ctx_mode' => array('Modus', 'Kontextmodus dieses Moduls.'),
                'payzen_sign_algo' => array('Signaturalgorithmus', 'Algorithmus zur Berechnung der Zahlungsformsignatur. Der ausgewählte Algorithmus muss derselbe sein, wie er im PayZen Back Office.' . (! $sha2 ? '<br /><b>Der HMAC-SHA-256-Algorithmus sollte nicht aktiviert werden, wenn er noch nicht im PayZen Back Office verfügbar ist. Die Funktion wird in Kürze verfügbar sein.</b>' : '')),
                'payzen_platform_url' => array('Plattform-URL', 'Link zur Bezahlungsplattform.'),
                'payzen_check_url' => array('Benachrichtigung-URL', 'URL, die Sie in Ihre PayZen Back Office kopieren sollen > Einstellung > Regeln der Benachrichtigungen.'),
                'payzen_payment_page' => array('ZAHLUNGSSEITE', null),
                'payzen_language' => array('Standardsprache', 'Standardsprache auf Zahlungsseite.'),
                'payzen_available_languages' => array('Verfügbare Sprachen', 'Verfügbare Sprachen der Zahlungsseite. Nichts auswählen, um die Einstellung der Zahlungsplattform zu benutzen.'),
                'payzen_capture_delay' => array('Einzugsfrist', 'Anzahl der Tage bis zum Einzug der Zahlung (Einstellung über Ihr PayZen Back Office).'),
                'payzen_validation_mode' => array('Bestätigungsmodus', 'Bei manueller Eingabe müssen Sie Zahlungen manuell in Ihr PayZen Back Office bestätigen.'),
                'payzen_payment_cards' => array('Kartentypen', 'Wählen Sie die zur Zahlung verfügbaren Kartentypen aus. Nichts auswählen, um die Einstellungen der Plattform zu verwenden.'),
                'payzen_selective_3ds' => array('SELEKTIVES 3DS', null),
                'payzen_3ds_min_amount' => array('3DS deaktivieren', 'Betrag, unter dem 3DS deaktiviert wird. Muss für die Option Selektives 3DS freigeschaltet sein. Weitere Informationen finden Sie in der Moduldokumentation.'),
                'payzen_amount_restrictions' => array('BETRAGSBESCHRÄNKUNGEN', null),
                'payzen_min_amount' => array('Mindestbetrag', 'Mindestbetrag für die Nutzung dieser Zahlungsweise.'),
                'payzen_max_amount' => array('Höchstbetrag', 'Höchstbetrag für die Nutzung dieser Zahlungsweise.'),
                'payzen_return_to_shop' => array('RÜCKKEHR ZUM LADEN', null),
                'payzen_redirect_enabled' => array('Automatische Weiterleitung', 'Ist diese Einstellung aktiviert, wird der Kunde am Ende des Bezahlvorgangs automatisch auf Ihre Seite weitergeleitet.'),
                'payzen_redirect_success_timeout' => array('Zeitbeschränkung Weiterleitung im Erfolgsfall', 'Zeitspanne in Sekunden (0-300) bis zur automatischen Weiterleitung des Kunden auf Ihre Seite nach erfolgter Zahlung.'),
                'payzen_redirect_success_message' => array('Weiterleitungs-Nachricht im Erfolgsfall', 'Nachricht, die nach erfolgter Zahlung und vor der Weiterleitung auf der Plattform angezeigt wird.'),
                'payzen_redirect_error_timeout' => array('Zeitbeschränkung Weiterleitung nach Ablehnung', 'Zeitspanne in Sekunden (0-300) bis zur automatischen Weiterleitung des Kunden auf Ihre Seite nach fehlgeschlagener Zahlung.'),
                'payzen_redirect_error_message' => array('Weiterleitungs-Nachricht nach Ablehnung', 'Nachricht, die nach fehlgeschlagener Zahlung und vor der Weiterleitung auf der Plattform angezeigt wird.'),
                'payzen_return_mode' => array('Übermittlungs-Modus', 'Methode, die zur Übermittlung des Zahlungsergebnisses von der Zahlungsschnittstelle an Ihren Shop verwendet wird.'),
                'payzen_payment_status_on_success' => array('Zahlungsstatus der Bestellung', 'Der Zahlungsstatus der bezahlten Bestellungen durch dieses Beszahlungsmittel definieren.')
            ),

            'fr_FR' => array(
                'payzen_developed_by' => array('Développé par', null),
                'payzen_contact_email' => array('Courriel de contact', null),
                'payzen_module_version' => array('Version du module', null),
                'payzen_gateway_version' => array('Version de la plateforme', null),
                'payzen_gateway_access' => array('ACCÈS À LA PLATEFORME', null),
                'payzen_site_id' => array('Identifiant de la boutique', 'Identifiant fourni par PayZen.'),
                'payzen_key_test' => array('Clé en mode test', 'Clé fournie par PayZen pour le mode test (disponible sur le Back Office PayZen).'),
                'payzen_key_prod' => array('Clé en mode production', 'Clé fournie par PayZen (disponible sur le Back Office PayZen après passage en production).'),
                'payzen_ctx_mode' => array('Mode', 'Mode de fonctionnement du module.'),
                'payzen_sign_algo' => array('Algorithme de signature', 'Algorithme utilisé pour calculer la signature du formulaire de paiement. L\'algorithme sélectionné doit être le même que celui configuré sur le Back Office PayZen.' . (! $sha2 ? '<br /><b>Le HMAC-SHA-256 ne doit pas être activé si celui-ci n\'est pas encore disponible depuis le Back Office PayZen, la fonctionnalité sera disponible prochainement.</b>' : '')),
                'payzen_platform_url' => array('URL de la page de paiement', 'URL vers laquelle l\'acheteur sera redirigé pour le paiement.'),
                'payzen_check_url' => array('URL de notification', 'URL à copier dans le Back Office PayZen > Paramétrage > Règles de notifications.'),
                'payzen_payment_page' => array('PAGE DE PAIEMENT', null),
                'payzen_language' => array('Langue par défaut', 'Sélectionner la langue par défaut à utiliser sur la page de paiement.'),
                'payzen_available_languages' => array('Langues disponibles', 'Sélectionner les langues à proposer sur la page de paiement. Ne rien sélectionner pour utiliser la configuration de la plateforme.'),
                'payzen_capture_delay' => array('Délai avant remise en banque', 'Le nombre de jours avant la remise en banque (paramétrable sur votre Back Office PayZen).'),
                'payzen_validation_mode' => array('Mode de validation', 'En mode manuel, vous devrez confirmer les paiements dans le Back Office PayZen.'),
                'payzen_payment_cards' => array('Types de carte', 'Le(s) type(s) de carte pouvant être utilisé(s) pour le paiement. Ne rien sélectionner pour utiliser la configuration de la plateforme.'),
                'payzen_selective_3ds' => array('3DS SÉLECTIF', null),
                'payzen_3ds_min_amount' => array('Désactiver 3DS', 'Montant en dessous duquel 3DS sera désactivé. Nécessite la souscription à l\'option 3DS sélectif. Pour plus d\'informations, reportez-vous à la documentation du module.'),
                'payzen_amount_restrictions' => array('RESTRICTIONS SUR LE MONTANT', null),
                'payzen_min_amount' => array('Montant minimum', 'Montant minimum pour lequel cette méthode de paiement est disponible.'),
                'payzen_max_amount' => array('Montant maximum', 'Montant maximum pour lequel cette méthode de paiement est disponible.'),
                'payzen_return_to_shop' => array('RETOUR À LA BOUTIQUE', null),
                'payzen_redirect_enabled' => array('Redirection automatique', 'Si activée, l\'acheteur sera redirigé automatiquement vers votre site à la fin du paiement.'),
                'payzen_redirect_success_timeout' => array('Temps avant redirection (succès)', 'Temps en secondes (0-300) avant que l\'acheteur ne soit redirigé automatiquement vers votre site lorsque le paiement a réussi.'),
                'payzen_redirect_success_message' => array('Message avant redirection (succès)', 'Message affiché sur la page de paiement avant redirection lorsque le paiement a réussi.'),
                'payzen_redirect_error_timeout' => array('Temps avant redirection (échec)', 'Temps en secondes (0-300) avant que l\'acheteur ne soit redirigé automatiquement vers votre site lorsque le paiement a échoué.'),
                'payzen_redirect_error_message' => array('Message avant redirection (échec)', 'Message affiché sur la page de paiement avant redirection, lorsque le paiement a échoué.'),
                'payzen_return_mode' => array('Mode de retour', 'Façon dont l\'acheteur transmettra le résultat du paiement lors de son retour à la boutique.'),
                'payzen_payment_status_on_success' => array('Statut de paiement de la commande', 'Définir le statut de paiement des commandes payées par ce mode de paiement.')
            ),

            'es_ES' => array(
                'payzen_developed_by' => array('Desarrollado por', null),
                'payzen_contact_email' => array('Courriel de contact', null),
                'payzen_module_version' => array('Versión del módulo', null),
                'payzen_gateway_version' => array('Versión del portal', null),
                'payzen_gateway_access' => array('ACCESO AL PORTAL DE PAGO', null),
                'payzen_site_id' => array('Identificador de tienda', 'El identificador proporcionado por PayZen.'),
                'payzen_key_test' => array('Clave en modo test', 'Clave proporcionada por PayZen para modo test (disponible en el Back Office PayZen).'),
                'payzen_key_prod' => array('Clave en modo production', 'Clave proporcionada por PayZen (disponible en el Back Office PayZen después de habilitar el modo production).'),
                'payzen_ctx_mode' => array('Modo', 'El modo de contexto de este módulo.'),
                'payzen_sign_algo' => array('Algoritmo de firma', 'Algoritmo usado para calcular la firma del formulario de pago. El algoritmo seleccionado debe ser el mismo que el configurado en el Back Office PayZen.' . (! $sha2 ? '<br /><b>El algoritmo HMAC-SHA-256 no se debe activar si aún no está disponible el Back Office PayZen, la función estará disponible pronto.' : '')),
                'payzen_platform_url' => array('URL de página de pago', 'Enlace a la página de pago.'),
                'payzen_check_url' => array('URL de notificación de pago instantáneo', 'URL a copiar en el Back Office PayZen > Configuración > Reglas de notificación.'),
                'payzen_payment_page' => array('PÁGINA DE PAGO', null),
                'payzen_language' => array('Idioma por defecto', 'Idioma por defecto en la página de pago.'),
                'payzen_available_languages' => array('Idiomas disponibles', 'Idiomas disponibles en la página de pago. Si no selecciona ninguno, todos los idiomas compatibles estarán disponibles.'),
                'payzen_capture_delay' => array('Plazo de captura', 'El número de días antes de la captura del pago (ajustable en su Back Office PayZen).'),
                'payzen_validation_mode' => array('Modo de validación', 'Si se selecciona manual, deberá confirmar los pagos manualmente en su Back Office PayZen.'),
                'payzen_payment_cards' => array('Tipos de tarjeta', 'El tipo(s) de tarjeta que se puede usar para el pago. No haga ninguna selección para usar la configuración del portal.'),
                'payzen_selective_3ds' => array('3DS SELECTIVO', null),
                'payzen_3ds_min_amount' => array('Deshabilitar 3DS', 'Monto por debajo del cual se deshabilitará 3DS. Requiere suscripción a la opción 3DS selectivo. Para más información, consulte la documentación del módulo.'),
                'payzen_amount_restrictions' => array('RESTRICCIONES DE MONTO', null),
                'payzen_min_amount' => array('Monto mínimo', 'Monto mínimo para activar este método de pago.'),
                'payzen_max_amount' => array('Monto máximo', 'Monto máximo para activar este método de pago.'),
                'payzen_return_to_shop' => array('VOLVER A LA TIENDA', null),
                'payzen_redirect_enabled' => array('Redirección automática', 'Si está habilitada, el comprador es redirigido automáticamente a su sitio al final del pago.'),
                'payzen_redirect_success_timeout' => array('Tiempo de espera de la redirección en pago exitoso', 'Tiempo en segundos (0-300) antes de que el comprador sea redirigido automáticamente a su sitio web después de un pago exitoso.'),
                'payzen_redirect_success_message' => array('Mensaje de redirección en pago exitoso', 'Mensaje mostrado en la página de pago antes de la redirección después de un pago exitoso.'),
                'payzen_redirect_error_timeout' => array('Tiempo de espera de la redirección en pago rechazado', 'Tiempo en segundos (0-300) antes de que el comprador sea redirigido automáticamente a su sitio web después de un pago rechazado.'),
                'payzen_redirect_error_message' => array('Mensaje de redirección en pago rechazado', 'Mensaje mostrado en la página de pago antes de la redirección después de un pago rechazado.'),
                'payzen_return_mode' => array('Modo de retorno', 'Método que se usará para transmitir el resultado del pago de la página de pago a su tienda.'),
                'payzen_payment_status_on_success' => array('Estado de pago del pedido', 'Define el estado de pago de los pedidos pagados con este modo de pago.')
            )
        );

        $shopRepository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Locale');
        foreach ($translations as $locale => $snippets) {
            $localeModel = $shopRepository->findOneBy(array('locale' => $locale));
            if ($localeModel === null) { // Unsupported language.
                continue;
            }

            foreach ($snippets as $element => $snippet) {
                $elementModel = $form->getElement($element);
                if ($elementModel === null) { // Undefined element with such text.
                    continue;
                }

                $translationModel = new Shopware\Models\Config\ElementTranslation();
                $translationModel->setLabel($snippet[0]);
                $translationModel->setDescription($snippet[1]);
                $translationModel->setLocale($localeModel);
                $elementModel->addTranslation($translationModel);
            }
        }
    }

    /**
     * Create and subscribe the events and hooks.
     */
    protected function createMyEvents()
    {
        $this->subscribeEvent(
            'Shopware_Modules_Admin_GetPaymentMeans_DataFilter',
            'onFilterPaymentMethods'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Dispatcher_ControllerPath_Frontend_PaymentPayzen',
            'onPaymentPayzen'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Front_RouteShutdown',
            'onRouteShutdown',
            -500 // Must be the highest priority.
        );

        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout',
            'onCheckoutConfirm'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout',
            'onCheckoutFinish'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Frontend_Index',
            'onHomeDisplay'
        );

        $this->subscribeEvent(
            'Enlight_Controller_Action_PostDispatch_Backend_Order',
            'onOrderGetList'
        );
    }

    public function onFilterPaymentMethods(Enlight_Event_EventArgs $args)
    {
        $paymentMeans = $args->getReturn();

        $request = Shopware()->Front()->Request();
        if ($request->getControllerName() === 'account'
                && $request->getActionName() === 'payment'
                    && $request->getParam('sTarget', $request->getControllerName()) !== 'checkout') {
            return $paymentMeans;
        }

        $currency = Shopware()->Currency()->getShortName(); // Current shop currency.
        $amount = Shopware()->Session()->sBasketAmount; // Current basket amount.
        $min = $this->Config()->get('payzen_min_amount'); // Min amount to activate this module.
        $max = $this->Config()->get('payzen_max_amount'); // Max amount to activate this module.

        if (($min && $amount < $min) || ($max && $amount > $max) || ! PayzenApi::findCurrencyByAlphaCode($currency)) {
            foreach ($paymentMeans as $key => $method) {
                if ($method['name'] === 'payzen') {
                    unset($paymentMeans[$key]);
                    break;
                }
            }
        }

        return $paymentMeans;
    }

    public function onPaymentPayzen(Enlight_Event_EventArgs $args)
    {
        $this->Application()->Template()->addTemplateDir($this->Path() . 'Views/', 'payzen');
        return $this->Path() . 'Controllers/Frontend/PaymentPayzen.php';
    }

    public function onRouteShutdown(Enlight_Event_EventArgs $args) {
        $request = $args->getSubject()->Request();

        if ($request->getControllerName() === 'payment_payzen' && $request->getActionName() === 'process') {
            // Rename vads_order_id parameter to avoid ShopWare filter on "s_order_"-like strings.
            if ($request->getParam('vads_order_id')) {
                $request->setParam('tmp_order_id', $request->getParam('vads_order_id'));
            }

            // Rename vads_order_info parameter to avoid ShopWare filter on "s_order_"-like strings.
            if ($request->getParam('vads_order_info')) {
                $request->setParam('tmp_order_info', $request->getParam('vads_order_info'));
            }
        }
    }

    public function onCheckoutConfirm(Enlight_Event_EventArgs $args)
    {
        $request = $args->getRequest();
        $view = $args->getSubject()->View();

        if ($request->getActionName() !== 'confirm') {
            return;
        }

        $session = Shopware()->Session();
        if ($session->payzenPaymentResult) {
            $this->Application()->Snippets()->addConfigDir($this->Path() . 'Snippets/');
            $view->addTemplateDir($this->Path() . 'Views/', 'payzen');

            $view->extendsTemplate('frontend/checkout/payzen_confirm.tpl');

            $view->assign('payzenPaymentResult', $session->payzenPaymentResult);
            $session->offsetUnset('payzenPaymentResult');
        }
    }

    public function onCheckoutFinish(Enlight_Event_EventArgs $args)
    {
        $request = $args->getSubject()->Request();
        $view = $args->getSubject()->View();

        if ($request->getActionName() !== 'finish') {
            return;
        }

        $this->Application()->Snippets()->addConfigDir($this->Path() . 'Snippets/');

        $view->addTemplateDir($this->Path() . 'Views/', 'payzen');
        $view->extendsTemplate('frontend/checkout/payzen_finish.tpl');

        $session = Shopware()->Session();
        if ($session->payzenGoingIntoProductionInfo) {
            $view->assign('payzenGoingIntoProductionInfo', $session->payzenGoingIntoProductionInfo);
            $session->offsetUnset('payzenGoingIntoProductionInfo');
        }

        if ($session->payzenCheckUrlWarn) {
            $view->assign('payzenCheckUrlWarn', $session->payzenCheckUrlWarn);
            $session->offsetUnset('payzenCheckUrlWarn');

            $view->assign('payzenShopOffline', $this->Application()->Config()->get('setoffline'));
        }
    }

    public function onHomeDisplay(Enlight_Event_EventArgs $args)
    {
        $request = $args->getRequest();
        $view = $args->getSubject()->View();

        if ($request->getActionName() !== 'index') {
            return;
        }

        $session = Shopware()->Session();
        if ($session->payzenPaymentError) {
            $this->Application()->Snippets()->addConfigDir($this->Path() . 'Snippets/');
            $view->addTemplateDir($this->Path() . 'Views/', 'payzen');

            $view->extendsTemplate('frontend/home/payzen_index.tpl');

            $session->offsetUnset('payzenPaymentError');
        }
    }

    public function onOrderGetList(Enlight_Event_EventArgs $args)
    {
        $request = $args->getSubject()->Request();
        $view = $args->getSubject()->View();

        if ($request->getActionName() !== 'getList' && $request->getActionName() !== 'load') {
            return;
        }

        $this->Application()->Snippets()->addConfigDir($this->Path() . 'Snippets/');

        $view->addTemplateDir($this->Path() . 'Views/');
        $view->extendsTemplate('backend/payzen/order/view/detail/overview.js');
    }
}
