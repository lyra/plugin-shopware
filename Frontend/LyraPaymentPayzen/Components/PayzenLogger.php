<?php
/**
 * Copyright © Lyra Network.
 * This file is part of PayZen plugin for ShopWare. See COPYING.md for license details.
 *
 * @author    Lyra Network (https://www.lyra.com/)
 * @copyright Lyra Network
 * @license   http://www.gnu.org/licenses/agpl.html GNU Affero General Public License (AGPL v3)
 */

// Define root directory.
if (is_dir(Shopware()->DocPath() . 'var')) {
    define('PAYZEN_LOG_DIRECTORY', Shopware()->DocPath() . 'var' . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR);
} else {
    if (! is_dir(Shopware()->DocPath() . 'logs')) {
        mkdir(Shopware()->DocPath() . 'logs');
    }

    define('PAYZEN_LOG_DIRECTORY', Shopware()->DocPath() . 'logs' . DIRECTORY_SEPARATOR);
}

if (class_exists('Shopware\Components\Logger') && class_exists('Monolog\Handler\StreamHandler')) {
    class PayzenLogger extends \Shopware\Components\Logger
    {
        const LOG_FILE_NAME = 'payzen.log';
        const LOG_LEVEL = \Shopware\Components\Logger::INFO;

        public function __construct($name)
        {
            parent::__construct($name);

            $this->pushHandler(
                new \Monolog\Handler\StreamHandler(PAYZEN_LOG_DIRECTORY . self::LOG_FILE_NAME, self::LOG_LEVEL)
            );
        }
    }

} else { // For backward compatibility.
    class PayzenLogger
    {
        const DEBUG = 1;
        const INFO = 2;
        const WARN = 3;
        const ERROR = 4;

        private $levels = array(
            self::DEBUG => 'DEBUG',
            self::INFO => 'INFO',
            self::WARN => 'WARN',
            self::ERROR => 'ERROR'
        );

        const LOG_FILE_NAME = 'payzen.log';
        const LOG_LEVEL = self::INFO;

        private $path;
        private $name;

        public function __construct($name)
        {
            $this->name = $name;
            $this->path = PAYZEN_LOG_DIRECTORY . self::LOG_FILE_NAME;
        }

        public function log($msg, $msgLevel = self::INFO)
        {
            if ($msgLevel < 1 || $msgLevel > 4) {
                $msgLevel = self::INFO;
            }

            if ($msgLevel < self::LOG_LEVEL) {
                // No logs.
                return;
            }

            $date = date('Y-m-d H:i:s', time());

            $fLog = @fopen($this->path, 'a');
            if ($fLog) {
                fwrite($fLog, "[$date] {$this->name}.{$this->levels[$msgLevel]}: $msg\n");
                fclose($fLog);
            }
        }

        public function debug($msg)
        {
            $this->log($msg, self::DEBUG);
        }

        public function info($msg)
        {
            $this->log($msg, self::INFO);
        }

        public function warn($msg)
        {
            $this->log($msg, self::WARN);
        }

        public function error($msg)
        {
            $this->log($msg, self::ERROR);
        }
    }
}
