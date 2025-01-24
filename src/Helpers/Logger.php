<?php

declare(strict_types=1);

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

    use Cdek\Model\Log;
    use WC_Logger_Interface;

    class Logger
    {
        public const EXCEPTION_CONTEXT = 'exception';
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

        /** @noinspection PhpUnused */
        public static function debug(Log $obLog): void
        {
            if ( $log = static::init()->logger ) {
                $log->debug($obLog->getMessage(), $obLog->getLog());
            }
        }

        /** @noinspection PhpUnused */
        public static function info(Log $obLog): void
        {
            if ( $log = static::init()->logger ) {
                $log->info($obLog->getMessage(), $obLog->getLog());
            }
        }

        /** @noinspection PhpUnused */
        public static function notice(Log $obLog): void
        {
            if ( $log = static::init()->logger ) {
                $log->notice($obLog->getMessage(), $obLog->getLog());
            }
        }

        /** @noinspection PhpUnused */
        public static function warning(Log $obLog): void
        {
            if ( $log = static::init()->logger ) {
                $log->warning($obLog->getMessage(), $obLog->getLog());
            }
        }

        /** @noinspection PhpUnused */
        public static function error(Log $obLog): void
        {
            if ( $log = static::init()->logger ) {
                $log->error($obLog->getMessage(), $obLog->getLog());
            }
        }

        /** @noinspection PhpUnused */
        public static function critical(Log $obLog): void
        {
            if ( $log = static::init()->logger ) {
                $log->critical($obLog->getMessage(), $obLog->getLog());
            }
        }

        /** @noinspection PhpUnused */
        public static function alert(Log $obLog): void
        {
            if ( $log = static::init()->logger ) {
                $log->alert($obLog->getMessage(), $obLog->getLog());
            }
        }

        /** @noinspection PhpUnused */
        public static function emergency(Log $obLog): void
        {
            if ( $log = static::init()->logger ) {
                $log->emergency($obLog->getMessage(), $obLog->getLog());
            }
        }
    }
}
