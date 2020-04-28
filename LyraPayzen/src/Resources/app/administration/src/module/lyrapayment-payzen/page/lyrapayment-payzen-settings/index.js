/**
 * Copyright © Lyra Network.
 * This file is part of PayZen for Shopware. See COPYING.md for license details.
 *
 * @author    Lyra Network <https://www.lyra.com>
 * @copyright Lyra Network
 * @license   https://opensource.org/licenses/mit-license.html The MIT License (MIT)
 */

const { Component, Mixin } = Shopware;
import template from './lyrapayment-payzen-settings.html.twig';
import './lyrapayment-payzen-settings-style.scss';

Component.register('lyrapayment-payzen-settings', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('sw-inline-snippet')
    ],

    inject: [ 'LyraPaymentPayzenSettingsService' ],

    data() {
        return {
            isSupportModalOpen: false,
            paymentStatuses: [],
            languages: [],
            cardTypes: [],
            docFiles: [],
            isdocModalOpen: false,
            qualif: false,
            shatwo: true
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            var me = this;

            this.LyraPaymentPayzenSettingsService.getFeatures()
                .then((result) => {
                    me.qualif = result.qualif;
                    me.shatwo = result.shatwo;
                });

            this.LyraPaymentPayzenSettingsService.getDocFiles()
                .then((result) => {
                    result.data.forEach((element) => {
                        me.docFiles.push({
                            "name": element.name,
                            "title": element.title,
                            "link": element.link,
                        })
                    });
                });

            this.LyraPaymentPayzenSettingsService.getCardTypes()
                .then((result) => {
                    for(let key in result.data) {
                        if(result.data.hasOwnProperty(key)) {
                            me.cardTypes.push({
                                "label": result.data[key],
                                "value": key,
                            });
                        }
                    }
                });

            this.LyraPaymentPayzenSettingsService.getLanguages()
                .then((result) => {
                    for(let key in result.data) {
                        if(result.data.hasOwnProperty(key)) {
                            me.languages.push({
                                "label": this.$tc('lyrapayment-payzen.payzenLanguages.' + key),
                                "value": key,
                            });
                        }
                    }
                });

            this.LyraPaymentPayzenSettingsService.getPaymentStatuses()
                .then((result) => {
                    result.data.forEach((element) => {
                        me.paymentStatuses.push({
                            "label": element.label,
                            "value": element.value,
                        })
                    });
                });
        },

        getBind(element) {
            if (element.name === 'LyraPaymentPayzen.config.payzenSignAlgo' && ! this.shatwo) {
                    element.config.helpText = this.$tc('lyrapayment-payzen.payzenSignAlgoDesc');
            }

            return element;
        },

        isDisabled(element) {
            return (element.name.startsWith('LyraPaymentPayzen.config.payzenCtxMode') && this.qualif);
        },

        isShown(element) {
            return (! element.name.startsWith('LyraPaymentPayzen.config.payzenKeyTest') || ! this.qualif);
        },

        onSave() {
            this.$refs.systemConfig.saveAll().then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-plugin-config.titleSaveSuccess'),
                    message: this.$tc('sw-plugin-config.messageSaveSuccess')
                });
            }).catch((err) => {
                this.createNotificationError({
                    title: this.$tc('sw-plugin-config.titleSaveError'),
                    message: err
                });
            });
        }
    }
});