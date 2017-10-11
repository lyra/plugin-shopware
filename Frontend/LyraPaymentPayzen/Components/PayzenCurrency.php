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

if (! class_exists('PayzenCurrency', false)) {

    /**
     * Class representing a currency, used for converting alpha/numeric ISO codes and float/integer amounts.
     */
    class PayzenCurrency
    {

        private $alpha3;
        private $num;
        private $decimals;

        public function __construct($alpha3, $num, $decimals = 2)
        {
            $this->alpha3 = $alpha3;
            $this->num = $num;
            $this->decimals = $decimals;
        }

        public function convertAmountToInteger($float)
        {
            $coef = pow(10, $this->decimals);

            $amount = $float * $coef;
            return (int) (string) $amount; // cast amount to string (to avoid rounding) than return it as int
        }

        public function convertAmountToFloat($integer)
        {
            $coef = pow(10, $this->decimals);

            return ((float) $integer) / $coef;
        }

        public function getAlpha3()
        {
            return $this->alpha3;
        }

        public function getNum()
        {
            return $this->num;
        }

        public function getDecimals()
        {
            return $this->decimals;
        }
    }
}
