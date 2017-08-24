<?php
/**
 * PayZen V2-Payment Module version 1.1.0 for ShopWare 4.x-5.x. Support contact : support@payzen.eu.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @category  payment
 * @package   payzen
 * @author    Lyra Network (http://www.lyra-network.com/)
 * @copyright 2014-2016 Lyra Network and contributors
 * @license   http://www.gnu.org/licenses/agpl.html  GNU Affero General Public License (AGPL v3)
 */

// root directory
if (is_dir(Shopware()->OldPath() . 'var')) {
	define('PAYZEN_LOG_DIRECTORY', Shopware()->OldPath() . 'var' . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR);
} else {
	if (!is_dir(Shopware()->OldPath() . 'logs')) {
		mkdir(Shopware()->OldPath() . 'logs');
	}

	define('PAYZEN_LOG_DIRECTORY', Shopware()->OldPath() . 'logs' . DIRECTORY_SEPARATOR);
}

if ((class_exists('Shopware\Components\Logger')) && (class_exists('Monolog\Handler\StreamHandler'))) {
	class PayzenLogger extends \Shopware\Components\Logger {

		const LOG_FILE_NAME = 'payzen.log';
		const LOG_LEVEL = \Shopware\Components\Logger::INFO;

		public function __construct($name) {
			parent::__construct($name);

			$this->pushHandler(new \Monolog\Handler\StreamHandler(PAYZEN_LOG_DIRECTORY . self::LOG_FILE_NAME, self::LOG_LEVEL));
		}
	}

} else { // for backward compatibility

	class PayzenLogger {
		const DEBUG = [1, 'DEBUG'];
		const INFO = [2, 'INFO'];
		const WARN = [3, 'WARN'];
		const ERROR = [4, 'ERROR'];

		const LOG_FILE_NAME = 'payzen.log';
		const LOG_LEVEL = self::INFO;

		private $path;
		private $name;

		public function __construct($name) {
			$this->name = $name;
			$this->path = PAYZEN_LOG_DIRECTORY . self::LOG_FILE_NAME;
		}

		public function log($msg, $msgLevel = self::INFO) {
			if(!is_array($msgLevel) || !isset($msgLevel[0]) || !is_numeric($msgLevel[0])) {
				$msgLevel = self::INFO;
			}

			if($msgLevel[0] < self::LOG_LEVEL[0]) {
				// no logs
				return ;
			}

			$date = date('Y-m-d H:i:s', time());

			$fLog = @fopen($this->path, 'a');
			if ($fLog) {
				fwrite($fLog, "[$date] {$this->name}.{$msgLevel[1]}: $msg\n");
				fclose($fLog);
			}
		}

		public function debug($msg) {
			$this->log($msg, self::DEBUG);
		}

		public function info($msg) {
			$this->log($msg, self::INFO);
		}

		public function warn($msg) {
			$this->log($msg, self::WARN);
		}

		public function error($msg) {
			$this->log($msg, self::ERROR);
		}
	}
}
