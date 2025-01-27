<?php

declare(strict_types=1);

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\Model\Log;
    use WC_Logger_Interface;
    use Throwable;

    class Logger
    {
        private static ?Logger $instance = null;
        private ?WC_Logger_Interface $logger = null;

        public function __construct()
        {
            $logger = wc_get_logger();

            if ( $logger instanceof WC_Logger_Interface ){
                $this->logger = $logger;
            }
        }

        public static function init(): Logger
        {
            if(static::$instance === null){
                static::$instance = new static();
            }

            return static::$instance;
        }

        /**
         * @noinspection PhpUnused
         * @param  string  $message
         * @param array|Throwable $context
         *
         * @return void
         */
        public static function debug(string $message, $context = []): void
        {
            if ( ($log = static::init()->logger) && ($obLog = static::initLog($message, $context))) {
                $log->debug($obLog->getMessage(), $obLog->getLog());
            }
        }

        /**
         * @noinspection PhpUnused
         * @param  string  $message
         * @param array|Throwable $context
         *
         * @return void
         */
        public static function info(string $message, $context = []): void
        {
            if ( ($log = static::init()->logger) && ($obLog = static::initLog($message, $context))) {
                $log->info($obLog->getMessage(), $obLog->getLog());
            }
        }

        /**
         * @noinspection PhpUnused
         * @param  string  $message
         * @param array|Throwable $context
         *
         * @return void
         */
        public static function notice(string $message, $context = []): void
        {
            if ( ($log = static::init()->logger) && ($obLog = static::initLog($message, $context))) {
                $log->notice($obLog->getMessage(), $obLog->getLog());
            }
        }


        /**
         * @noinspection PhpUnused
         * @param  string  $message
         * @param array|Throwable $context
         *
         * @return void
         */
        public static function warning(string $message, $context = []): void
        {
            if ( ($log = static::init()->logger) && ($obLog = static::initLog($message, $context))) {
                $log->warning($obLog->getMessage(), $obLog->getLog());
            }
        }

        /**
         * @noinspection PhpUnused
         * @param  string  $message
         * @param array|Throwable $context
         *
         * @return void
         */
        public static function error(string $message, $context = []): void
        {
            if ( ($log = static::init()->logger) && ($obLog = static::initLog($message, $context))) {
                $log->error($obLog->getMessage(), $obLog->getLog());
            }
        }

        /**
         * @noinspection PhpUnused
         * @param  string  $message
         * @param array|Throwable $context
         *
         * @return void
         */
        public static function critical(string $message, $context = []): void
        {
            if ( ($log = static::init()->logger) && ($obLog = static::initLog($message, $context))) {
                $log->critical($obLog->getMessage(), $obLog->getLog());
            }
        }

        /**
         * @noinspection PhpUnused
         * @param  string  $message
         * @param array|Throwable $context
         *
         * @return void
         */
        public static function alert(string $message, $context = []): void
        {
            if ( ($log = static::init()->logger) && ($obLog = static::initLog($message, $context))) {
                $log->alert($obLog->getMessage(), $obLog->getLog());
            }
        }

        /**
         * @noinspection PhpUnused
         * @param  string  $message
         * @param array|Throwable $context
         *
         * @return void
         */
        public static function emergency(string $message, $context = []): void
        {
            if ( ($log = static::init()->logger) && ($obLog = static::initLog($message, $context))) {
                $log->emergency($obLog->getMessage(), $obLog->getLog());
            }
        }

        private static function initLog(string $message, $context = []): Log
        {
            if ( $context instanceof Throwable ) {
                return Log::initWithException($message, $context);
            }

            return Log::initWithContext($message, $context);
        }
    }
}
