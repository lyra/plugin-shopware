# Lyra Collect for Shopware

Lyra Collect for Shopware is an open source plugin that links e-commerce websites based on Shopware to Lyra Collect secure payment gateway developed by [Lyra Network](https://www.lyra.com/).

## Installation & Upgrade

To update the payment plugin, you must first uninstall and delete the previous version. Make sure you saved the parameters of your plugin before deleting it.

To install the plugin, follow these steps:
- Goto `Extensions > My extensions` menu.
- Upload `LyranetworkLyra` directory to `[SHOPWARE]/custom/plugins/` via FTP or upload plugin ZIP from Shopware administration interface.
- Click on "Install app" link corresponding to the `Lyra Collect` plugin entry.
- Click on the checkbox (corresponding to Lyra Collect) in the `Active` column to (de)activate the plugin. If you cannot click on the button, the plugin is not installed.

Once activated, you can add the payment method `Lyra Collect` to your sales channel from your Shopware administration interface (if it's not already added):
- Click on your sales channel from the list displayed below the `Sales Channels` menu.
- Goto `General > General Settings` section.
- Add `Lyra Collect` to the list of available payment methods, then click the `Save` button.

## Configuration

In the Shopware administration interface:
- Go to `Settings > Extensions`.
- Click on `Lyra Collect` to display the plugin configuration interface.
- The payment plugin configuration interface is composed of several sections. Set your gateway credentials in "PAYMENT GATEWAY ACCESS" section then click the `Save` button.

## License

Each Lyra Collect payment module source file included in this distribution is licensed under the The MIT License (MIT).

Please see LICENSE.txt for the full text of the MIT license. It is also available through the world-wide-web at this URL: https://opensource.org/licenses/mit-license.html.