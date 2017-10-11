<?php
/**
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
 */

require_once 'Components/PayzenApi.php';

class Shopware_Plugins_Frontend_LyraPaymentPayzen_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{

    /**
     * Installs the plugin :
     *  - create and save the payment row
     *  - create the payment table
     *  - create admin form translations
     *  - create and subscribe the events and hooks
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

        // enable our plugin just after installation
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

        // remove img tags from additional description if any
        $ad = "\$additional.payment.additionaldescription|regex_replace:'#^<img[^>]+/>#':''";
        $content = preg_replace("@\\\$additional\.payment\.additionaldescription(\|regex_replace\:\'#\^<img\[\^>\]\+/>#\'\:\'\')*@", $ad, $content);

        // Make sure that the additionadescription field is evaluated by smarty
        $sql = "UPDATE `s_core_config_mails` SET `content` = ? WHERE `name` = 'sORDER'";
        Shopware()->Db()->query($sql, array($content));
    }

    private function fixBackendOrderDetails()
    {
        if (version_compare(Shopware::VERSION, '5.2.0', '<')) {
            return;
        }

        $sql = "SELECT COUNT(*) FROM `s_attribute_configuration` WHERE `table_name`= 's_order_attributes' AND `column_name` IN ('attribute1','attribute2','attribute3','attribute4','attribute5','attribute6')";
        $count = Shopware()->Db()->fetchOne($sql);

        if (! $count) {
            $sql = "INSERT INTO `s_attribute_configuration` (`table_name`, `column_name`, `column_type`, `position`,`translatable`, `display_in_backend`, `custom`,`label`)
                         VALUES ('s_order_attributes', 'attribute1', 'string', 1, 0, 1, 0, 'Message'),
                                ('s_order_attributes', 'attribute2', 'string', 2, 0, 1, 0, 'Transaction type'),
                                ('s_order_attributes', 'attribute3', 'string', 3, 0, 1, 0, 'Transaction ID'),
                                ('s_order_attributes', 'attribute4', 'string', 4, 0, 1, 0, 'Payment mean'),
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
            $sql = "SELECT COUNT(*) FROM `s_order` WHERE `paymentID`=?";
            $count = Shopware()->Db()->fetchOne($sql, array($payment->getId()));

            if (! $count) {
                // no orders associated with our payment mean, let's delete it
                $sql = "DELETE FROM `s_core_paymentmeans` WHERE `name` = 'payzen'";
                Shopware()->Db()->query($sql);
            }
        }

        // delete payment snippets
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
     * Activate the plugin.
     * Set the active flag in the payment row.
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

        // documentation path
        $rootDir = str_replace('\\', '/', Shopware()->OldPath());
        $path = str_replace('\\', '/', dirname(__FILE__)) . '/installation_doc/Integration_PayZen_ShopWare_4.x-5.x_v1.1.1.pdf';

        $relativePath = str_replace($rootDir, '', $path);

        return array(
            'version' => $this->getVersion(),
            'label' => $this->getLabel(),
            'description' => '<img src="data:image/png;base64,' . $logo . '" /> ' .
                             '<p>This plugin enables you to setup the PayZen payment system on your ShopWare website.</p>' .
                             '<a style="color: red;" href="'. Shopware()->Front()->Request()->getBasePath() . '/' . $relativePath.'" target="_blank">
                                Click here to view the module configuration documentation
                             </a>',
            'author' => 'Lyra Network',
            'copyright' => 'Copyright © 2015, Lyra Network',
            'license' => 'AGPLv3',
            'support' => 'support@payzen.eu',
            'link' => 'http://www.lyra-network.com/'
        );
    }

    /**
     * Returns the version of plugin as string.
     *
     * @return string
     */
    public function getVersion()
    {
        return '1.1.1';
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
                    VALUES (-3, 'payzen_cards', '', 'media/image/payzen_cards.png', 'IMAGE', 'png', " . filesize($mediaPath . $logo) . ", CURDATE())";
            Shopware()->Db()->query($sql);
        }

        $this->createPayment(array(
            'name' => 'payzen',
            'description' => 'PayZen',
            'additionalDescription' => '<img alt="PayZen" src="{link file="media/image/payzen_cards.png" fullPath}" />' .'<p>Payment by bank card<p/>',
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

        if (version_compare(Shopware::VERSION, '4.3.0', '>=')) {
            $ctxModes = array(
                array('PRODUCTION', array('de_DE' => 'PRODUKTION', 'en_GB' => 'PRODUCTION', 'fr_FR' => 'PRODUCTION')),
                array('TEST', array('de_DE' => 'TEST', 'en_GB' => 'TEST', 'fr_FR' => 'TEST'))
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
                )
            );
            foreach (PayzenApi::getSupportedLanguages() as $key => $language) {
                $languages[] = array($key, array(
                    'de_DE' => key_exists($key, $snippets['de_DE']) ? $snippets['de_DE'][$key] : $language,
                    'en_GB' => key_exists($key, $snippets['en_GB']) ? $snippets['en_GB'][$key] : $language,
                    'fr_FR' => key_exists($key, $snippets['fr_FR']) ? $snippets['fr_FR'][$key] : $language
                ));
            }

            $validationModes = array(
                array('', array('de_DE' => 'Einstellungen des Back Office', 'en_GB' => 'Back Office configuration', 'fr_FR' => 'Configuration Back Office')),
                array('0', array('de_DE' => 'Automatisch', 'en_GB' =>'Automatic', 'fr_FR' => 'Automatique')),
                array('1', array('de_DE' => 'Manuell', 'en_GB' =>'Manual', 'fr_FR' => 'Manuelle'))
            );

            foreach (PayzenApi::getSupportedCardTypes() as $key => $card) {
                $cards[] = array($key, $card);
            }

            $enableOptions = array(
                array('False', array('de_DE' => 'Deaktiviert', 'en_GB' => 'Disabled', 'fr_FR' => 'Désactivé')),
                array('True', array('de_DE' => 'Aktiviert', 'en_GB' => 'Enabled', 'fr_FR' => 'Activé'))
            );

            $returnModes = array(array('GET', 'GET'), array('POST', 'POST'));
        } else {
            $ctxModes = array('PRODUCTION', 'TEST');

            foreach (PayzenApi::getSupportedLanguages() as $key => $language) {
                $languages[] = array('value' => $key, 'label' => $language);
            }
            $languages = array('fields' => array('value', 'label'), 'data' => $languages);

            $validationModes = array(
                'fields' => array('value', 'label'),
                'data' => array(
                    array('value' => '', 'label' => 'Back Office configuration'),
                    array('value' => '0', 'label' => 'Automatic'),
                    array('value' => '1', 'label' => 'Manual')
                )
            );

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
        }

        // module information
        $form->setElement('text', 'payzen_developed_by', array(
            'label' => 'Developed by',
            'value' => 'http://www.lyra-network.com',
            'readOnly' => true,
            'fieldCls' => '',
            'focusCls' => '',
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('text', 'payzen_contact_email', array(
            'label' => 'Contact mail',
            'value' => 'support@payzen.eu',
            'readOnly' => true,
            'fieldCls' => '',
            'focusCls' => '',
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('text', 'payzen_module_version', array(
            'label' => 'Module version',
            'value' => '1.1.1',
            'readOnly' => true,
            'fieldCls' => '',
            'focusCls' => '',
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('text', 'payzen_gateway_version', array(
            'label' => 'Platform version',
            'value' => 'V2',
            'readOnly' => true,
            'fieldCls' => '',
            'focusCls' => '',
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        // payment access
        $form->setElement('text', 'payzen_site_id', array(
            'label' => 'Shop ID',
            'description' => 'The identifier provided by PayZen.',
            'value' => '12345678',
            'required' => true,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('text', 'payzen_key_test', array(
            'label' => 'Certificate in test mode',
            'description' => 'Certificate provided by your bank for test mode (available in PayZen Back Office).',
            'value' => '1111111111111111',
            'required' => true,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('text', 'payzen_key_prod', array(
            'label' => 'Certificate in production mode',
            'description' => 'Certificate provided by your bank (available in PayZen Back Office after enabling production mode).',
            'value' => '2222222222222222',
            'required' => true,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('select', 'payzen_ctx_mode', array(
            'label' => 'Mode',
            'description' => 'The context mode of this module.',
            'value' => 'TEST',
            'store' => $ctxModes,
            'editable' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));
        $form->setElement('text', 'payzen_platform_url', array(
            'label' => 'Payment page URL',
            'description' => 'Link to the payment page.',
            'value' => 'https://secure.payzen.eu/vads-payment/',
            'required' => true,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        // notification URL
        $shop = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop')->getActiveDefault();
        $baseUrl = $shop->getSecure() ?
                    'https://' . $shop->getSecureHost(). $shop->getSecureBasePath() :
                    'http://' . $shop->getHost() . $shop->getBasePath();

        $form->setElement('text', 'payzen_check_url', array(
            'label' => 'Notification URL',
            'description' => 'Notification URL to copy into your PayZen Back Office.',
            'value' => $baseUrl . '/payment_payzen/process',
            'readOnly' => true,
            'fieldCls' => '',
            'focusCls' => '',
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        // payment page
        $form->setElement('select', 'payzen_default_language', array(
            'label' => 'Default language',
            'description' => 'Default language on the payment page.',
            'value' => 'fr',
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
            'description' => 'The card type(s) that can be used for the payment. Select none to use platform configuration.',
            'value' => '',
            'store' =>  $cards,
            'displayField' => 'label',
            'valueField' => 'value',
            'editable' => false,
            'multiSelect' => true,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        // selective 3 DS
        $form->setElement('number', 'payzen_3ds_min_amount', array(
            'label' => 'Minimum amount to activate 3-DS',
            'description' => 'Needs subscription to Selective 3-D Secure option.',
            'required' => false,
            'scope' => Shopware\Models\Config\Element::SCOPE_SHOP
        ));

        // amount restrictions
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

        // return to shop
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
            'value' => 'Redirection vers la boutique dans quelques instants...',
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
            'value' => 'Redirection vers la boutique dans quelques instants...',
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
    }

    /**
     * Create module translations
     */
    protected function createMyTranslations()
    {
        $form = $this->Form();

        $translations = array(
            'de_DE' => array(
                'payzen_developed_by' => array('Entwickelt von', null),
                'payzen_contact_email' => array('Kontakt', null),
                'payzen_module_version' => array('Modulversion', null),
                'payzen_gateway_version' => array('Plattformversion', null),
                'payzen_site_id' => array('Site ID', 'Kennung, die von Ihrer Bank bereitgestellt wird.'),
                'payzen_key_test' => array('Zertifikat im Testbetrieb', 'Zertifikat, das von Ihrer Bank zu Testzwecken bereitgestellt wird (im PayZen-System verfügbar).'),
                'payzen_key_prod' => array('Zertifikat im Produktivbetrieb', 'Von Ihrer Bank bereitgestelltes Zertifikat (im PayZen-System verfügbar).'),
                'payzen_mode' => array('Modus', 'Kontextmodus dieses Moduls.'),
                'payzen_platform_url' => array('Plattform-URL', 'Link zur Bezahlungsplattform.'),
                'payzen_check_url' => array('Benachrichtigung-URL', 'URL vor Übertragung in Ihr PayZen prüfen.'),
                'payzen_default_language' => array('Standardsprache', 'Wählen Sie bitte die Spracheinstellung der Zahlungsseiten aus.'),
                'payzen_available_languages' => array('Verfügbare Sprachen', 'Verfügbare Sprachen der Zahlungsseite. Nichts auswählen, um die Einstellung der Zahlungsplattform zu benutzen.'),
                'payzen_capture_delay' => array('Einzugsfrist', 'Anzahl der Tage bis zum Einzug der Zahlung (Einstellung über Ihr PayZen-System).'),
                'payzen_validation_mode' => array('Bestätigungsmodus', 'Bei manueller Eingabe müssen Sie Zahlungen manuell in Ihrem Banksystem bestätigen.'),
                'payzen_payment_cards' => array('Verfügbare karten', 'Liste der/die für die Zahlung verfügbare(n) Kartentyp(en), durch Semikolon getrennt.'),
                'payzen_3ds_min_amount' => array('Mindestbetrag zur Aktivierung von 3DS', 'Muss für die Option Selektives 3-D Secure freigeschaltet sein.'),
                'payzen_min_amount' => array('Mindestbetrag', 'Mindestbetrag für die Nutzung dieser Zahlungsweise.'),
                'payzen_max_amount' => array('Höchstbetrag', 'Höchstbetrag für die Nutzung dieser Zahlungsweise.'),
                'payzen_redirect_enabled' => array('Automatische Weiterleitung', 'Ist diese Einstellung aktiviert, wird der Kunde am Ende des Bezahlvorgangs automatisch auf Ihre Seite weitergeleitet.'),
                'payzen_redirect_success_timeout' => array('Zeitbeschränkung Weiterleitung im Erfolgsfall', 'Zeitspanne in Sekunden (0-300) bis zur automatischen Weiterleitung des Kunden auf Ihre Seite nach erfolgter Zahlung.'),
                'payzen_redirect_success_message' => array('Weiterleitungs-Nachricht im Erfolgsfall', 'Nachricht, die nach erfolgter Zahlung und vor der Weiterleitung auf der Plattform angezeigt wird.'),
                'payzen_redirect_error_timeout' => array('Zeitbeschränkung Weiterleitung nach Ablehnung', 'Zeitspanne in Sekunden (0-300) bis zur automatischen Weiterleitung des Kunden auf Ihre Seite nach fehlgeschlagener Zahlung.'),
                'payzen_redirect_error_message' => array('Weiterleitungs-Nachricht nach Ablehnung', 'Nachricht, die nach fehlgeschlagener Zahlung und vor der Weiterleitung auf der Plattform angezeigt wird.'),
                'payzen_return_mode' => array('Übermittlungs-Modus', 'Methode, die zur Übermittlung des Zahlungsergebnisses von der Zahlungsschnittstelle an Ihren Shop verwendet wird.')
            ),

            'fr_FR' => array(
                'payzen_developed_by' => array('Développé par', null),
                'payzen_contact_email' => array('Courriel de contact', null),
                'payzen_module_version' => array('Version du module', null),
                'payzen_gateway_version' => array('Version de la plateforme', null),
                'payzen_site_id' => array('Identifiant de la boutique', 'Identifiant fourni par PayZen.'),
                'payzen_key_test' => array('Certificat en mode test', 'Certificat fourni par PayZen pour le mode test (disponible sur le Back Office de votre boutique).'),
                'payzen_key_prod' => array('Certificat en mode production', 'Certificat fourni par PayZen (disponible sur le Back Office de votre boutique après passage en production).'),
                'payzen_mode' => array('Mode', 'Mode de fonctionnement du module.'),
                'payzen_platform_url' => array('URL de la page de paiement', 'URL vers laquelle l\'acheteur sera redirigé pour le paiement.'),
                'payzen_check_url' => array('URL serveur', 'URL de notification à copier dans le Back Office PayZen.'),
                'payzen_default_language' => array('Langue par défaut', 'Sélectionner la langue par défaut à utiliser sur la page de paiement.'),
                'payzen_available_languages' => array('Langues disponibles', 'Sélectionner les langues à proposer sur la page de paiement.'),
                'payzen_capture_delay' => array('Délai avant remise en banque', 'Le nombre de jours avant la remise en banque (paramétrable sur votre Back Office PayZen).'),
                'payzen_validation_mode' => array('Mode de validation', 'En mode manuel, vous devrez confirmer les paiements dans le Back Office de votre boutique.'),
                'payzen_payment_cards' => array('Cartes disponibles', 'Le(s) type(s) de carte pouvant être utilisé(s) pour le paiement. Ne rien sélectionner pour utiliser la configuration de la plateforme.'),
                'payzen_3ds_min_amount' => array('Montant minimum pour lequel activer 3-DS', 'Nécessite la souscription à l\'option 3-D Secure sélectif.'),
                'payzen_min_amount' => array('Montant minimum', 'Montant minimum pour lequel cette méthode de paiement est disponible.'),
                'payzen_max_amount' => array('Montant maximum', 'Montant maximum pour lequel cette méthode de paiement est disponible.'),
                'payzen_redirect_enabled' => array('Redirection automatique', 'Si activée, l\'acheteur sera redirigé automatiquement vers votre site à la fin du paiement.'),
                'payzen_redirect_success_timeout' => array('Temps avant redirection (succès)', 'Temps en secondes (0-300) avant que l\'acheteur ne soit redirigé automatiquement vers votre site lorsque le paiement a réussi.'),
                'payzen_redirect_success_message' => array('Message avant redirection (succès)', 'Message affiché sur la page de paiement avant redirection lorsque le paiement a réussi.'),
                'payzen_redirect_error_timeout' => array('Temps avant redirection (échec)', 'Temps en secondes (0-300) avant que l\'acheteur ne soit redirigé automatiquement vers votre site lorsque le paiement a échoué.'),
                'payzen_redirect_error_message' => array('Message avant redirection (échec)', 'Message affiché sur la page de paiement avant redirection, lorsque le paiement a échoué.'),
                'payzen_return_mode' => array('Mode de retour', 'Façon dont l\'acheteur transmettra le résultat du paiement lors de son retour à la boutique.')
            )
        );

        $shopRepository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Locale');
        foreach ($translations as $locale => $snippets) {
            $localeModel = $shopRepository->findOneBy(array('locale' => $locale));
            if ($localeModel === null) { // unsupported language
                continue;
            }

            foreach ($snippets as $element => $snippet) {
                $elementModel = $form->getElement($element);
                if ($elementModel === null) { // undefined element with such text
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
            -500 // must be the highest priority
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
            'Enlight_Controller_Action_PostDispatch_Backend_Order',
            'onOrderGetList'
        );
    }

    public function onFilterPaymentMethods(Enlight_Event_EventArgs $args)
    {
        $paymentMeans = $args->getReturn();

        $request = Shopware()->Front()->Request();
        if ($request->getControllerName() == 'account'
                && $request->getActionName() == 'payment'
                        && $request->getParam('sTarget', $request->getControllerName()) != 'checkout') {
            return $paymentMeans;
        }

        $currency = Shopware()->Currency()->getShortName(); // current shop currency
        $amount = Shopware()->Session()->sBasketAmount; // current basket amount
        $min = $this->Config()->get('payzen_min_amount'); // min amount to activate this module
        $max = $this->Config()->get('payzen_max_amount'); // max amount to activate this module

        if (($min && $amount < $min) || ($max && $amount > $max) || ! PayzenApi::findCurrencyByAlphaCode($currency)) {
            foreach ($paymentMeans as $key => $mean) {
                if ($mean['name'] == 'payzen') {
                    unset($paymentMeans[$key]);
                    break;
                }
            }
        }

        return $paymentMeans;
    }

    public function onCheckoutConfirm(Enlight_Event_EventArgs $args)
    {
        $request = $args->getRequest();
        $view = $args->getSubject()->View();

        if ($request->getActionName() != 'confirm') {
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

    public function onPaymentPayzen(Enlight_Event_EventArgs $args)
    {
        $this->Application()->Template()->addTemplateDir($this->Path() . 'Views/', 'payzen');
        return $this->Path() . 'Controllers/Frontend/PaymentPayzen.php';
    }

    public function onRouteShutdown(Enlight_Event_EventArgs $args) {
        $request = $args->getSubject()->Request();

        if ($request->getControllerName() == 'payment_payzen' && $request->getActionName() == 'process') {
            // rename vads_order_id parameter to avoid ShopWare filter on "s_order_"-like strings
            if ($request->getParam('vads_order_id')) {
                $request->setParam('tmp_order_id', $request->getParam('vads_order_id'));
            }

            // rename vads_order_info parameter to avoid ShopWare filter on "s_order_"-like strings
            if ($request->getParam('vads_order_info')) {
                $request->setParam('tmp_order_info', $request->getParam('vads_order_info'));
            }

            // rename vads_order_info2 parameter to avoid ShopWare filter on "s_order_"-like strings
            if ($request->getParam('vads_order_info2')) {
                $request->setParam('tmp_order_info2', $request->getParam('vads_order_info2'));
            }
        }
    }

    public function onCheckoutFinish(Enlight_Event_EventArgs $args)
    {
        $request = $args->getSubject()->Request();
        $view = $args->getSubject()->View();

        if ($request->getActionName() != 'finish') {
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

    public function onOrderGetList(Enlight_Event_EventArgs $args)
    {
        $request = $args->getSubject()->Request();
        $view = $args->getSubject()->View();

        if ($request->getActionName() != 'getList' && $request->getActionName() != 'load') {
            return;
        }

        $this->Application()->Snippets()->addConfigDir($this->Path() . 'Snippets/');

        $view->addTemplateDir($this->Path() . 'Views/');
        $view->extendsTemplate('backend/payzen/order/view/detail/overview.js');
    }
}
