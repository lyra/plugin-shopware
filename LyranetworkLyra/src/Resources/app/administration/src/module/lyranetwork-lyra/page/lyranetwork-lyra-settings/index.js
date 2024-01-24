/**
 * Copyright © Lyra Network.
 * This file is part of Lyra Collect for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

const { Component, Mixin } = Shopware;
const SHOPWARE_VERSION = Shopware.Context.app.config.version;
import template from './lyranetwork-lyra-settings.html.twig';
import './lyranetwork-lyra-settings.scss';

Component.register('lyranetwork-lyra-settings', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet')
    ],

    inject: [ 'LyranetworkLyraSettingsService' ],

    data() {
        return {
            isSupportModalOpen: false,
            paymentStatuses: [],
            languages: [],
            cardTypes: [],
            docFiles: [],
            isdocModalOpen: false,
            qualif: false,
            shatwo: true,
            isFlow: false,
            smartform: true,
            cardDataModes: []
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            var me = this;

            this.LyranetworkLyraSettingsService.getFeatures()
                .then((result) => {
                    me.qualif = result.qualif;
                    me.shatwo = result.shatwo;
                    me.smartform = result.smartform;
                });

            this.LyranetworkLyraSettingsService.isFlow({'shopwareVersion': SHOPWARE_VERSION,})
                .then((result) => {
                    me.isFlow = result.isFlow;
                });

            this.LyranetworkLyraSettingsService.getDocFiles()
                .then((result) => {
                    result.data.forEach((element) => {
                        me.docFiles.push({
                            'name': element.name,
                            'title': element.title,
                            'link': element.link
                        })
                    });
                });

            this.LyranetworkLyraSettingsService.getCardTypes()
                .then((result) => {
                    for (let key in result.data) {
                        if (result.data.hasOwnProperty(key)) {
                            me.cardTypes.push({
                                'label': result.data[key],
                                'value': key
                            });
                        }
                    }
                });

            this.LyranetworkLyraSettingsService.getLanguages()
                .then((result) => {
                    for (let key in result.data) {
                        if (result.data.hasOwnProperty(key)) {
                            me.languages.push({
                                'label': this.$tc('lyraLanguages.' + key),
                                'value': key
                            });
                        }
                    }
                });

            this.LyranetworkLyraSettingsService.getCardDataModes()
                .then((result) => {
                    for (let key in result.data) {
                        if (result.data.hasOwnProperty(key)) {
                            me.cardDataModes.push({
                                'label': this.$tc('lyraCardDataModes.' + key),
                                'value': key
                            });
                        }
                    }
                });

            this.LyranetworkLyraSettingsService.getPaymentStatuses()
                .then((result) => {
                    result.data.forEach((element) => {
                        me.paymentStatuses.push({
                            'label': element.label,
                            'value': element.value
                        })
                    });
                });
        },

        getBind(element) {
            if (element.type=='single-select') {
                // Set the element's label.
                element.config.label = this.getElementLabel(element.name);
                
                // Set the element's Helptext.
                if (element.name === 'LyranetworkLyra.config.lyraSignAlgo' && ! this.shatwo) {
                    element.config.helpText = this.getElementHelptext(element.name + "1");
                } else {
                    element.config.helpText = this.getElementHelptext(element.name);
                }
            }

            return element;
        },

        getElementLabel(name) {
            return this.$tc(name.replace('LyranetworkLyra.config.', ''));
        },

        getElementHelptext(name) {
            return this.$tc(name.replace('LyranetworkLyra.config.', '') + 'Helptext');
        },

        isDisabled(element) {
            return (element.name.startsWith('LyranetworkLyra.config.lyraCtxMode') && this.qualif) || (element.name.startsWith('LyranetworkLyra.config.lyraOrderPlacedFlowEnabled') && ! this.isFlow);
        },

        isShown(element, config) {
            let restFields = [
                'LyranetworkLyra.config.lyraRestPopinMode',
                'LyranetworkLyra.config.lyraRestTheme',
                'LyranetworkLyra.config.lyraRestCompactMode',
                'LyranetworkLyra.config.lyraRestThreshold'
            ]
            if (restFields.includes(element.name)) {
                return config['LyranetworkLyra.config.lyraCardDataMode'] !== 'MODE_FORM';
            }

            return (! element.name.startsWith('LyranetworkLyra.config.lyraKeyTest') || ! this.qualif) && (! element.name.startsWith('LyranetworkLyra.config.lyraOrderPlacedFlowEnabled') || this.isFlow);
        },

        onSave() {
            this.$refs.systemConfig.saveAll().then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('titleSaveSuccess'),
                    message: this.$tc('messageSaveSuccess')
                });

	            const salesChannelId = this.$refs.systemConfig.currentSalesChannelId;
	            this.LyranetworkLyraSettingsService.setOrderPlacedFlow({'salesChannelId': salesChannelId, 'shopwareVersion': SHOPWARE_VERSION,});
            }).catch((err) => {
                this.createNotificationError({
                    title: this.$tc('titleSaveError'),
                    message: err
                });

	            const salesChannelId = this.$refs.systemConfig.currentSalesChannelId;
	            this.LyranetworkLyraSettingsService.setOrderPlacedFlow({'salesChannelId': salesChannelId, 'shopwareVersion': SHOPWARE_VERSION,});
            });

        }
    }
});