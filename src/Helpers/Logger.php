<?php

declare(strict_types=1);

namespace {

    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {

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
        public static function debug(string $message, array $context = []): void
        {
            if ( $log = static::init()->logger ) {
                $log->debug($message, static::exceptionParser($context));
            }
        }

        /** @noinspection PhpUnused */
        public static function info(string $message, array $context = []): void
        {
            if ( $log = static::init()->logger ) {
                $log->info($message, static::exceptionParser($context));
            }
        }

        /** @noinspection PhpUnused */
        public static function notice(string $message, array $context = []): void
        {
            if ( $log = static::init()->logger ) {
                $log->notice($message, static::exceptionParser($context));
            }
        }

        /** @noinspection PhpUnused */
        public static function warning(string $message, array $context = []): void
        {
            if ( $log = static::init()->logger ) {
                $log->warning($message, static::exceptionParser($context));
            }
        }

        /** @noinspection PhpUnused */
        public static function error(string $message, array $context = []): void
        {
            if ( $log = static::init()->logger ) {
                $log->error($message, static::exceptionParser($context));
            }
        }

        /** @noinspection PhpUnused */
        public static function critical(string $message, array $context = []): void
        {
            if ( $log = static::init()->logger ) {
                $log->critical($message, static::exceptionParser($context));
            }
        }

        /** @noinspection PhpUnused */
        public static function alert(string $message, array $context = []): void
        {
            if ( $log = static::init()->logger ) {
                $log->alert($message, static::exceptionParser($context));
            }
        }

        /** @noinspection PhpUnused */
        public static function emergency(string $message, array $context = []): void
        {
            if ( $log = static::init()->logger ) {
                $log->emergency($message, static::exceptionParser($context));
            }
        }

        private static function exceptionParser(array $context = []): array
        {
            if( isset($context[self::EXCEPTION_CONTEXT]) && $context[self::EXCEPTION_CONTEXT] instanceof \Throwable ){
                $context[self::EXCEPTION_CONTEXT] = [
                    'message' => $context[self::EXCEPTION_CONTEXT]->getMessage(),
                    'file'    => "{$context[self::EXCEPTION_CONTEXT]->getFile()}:{$context[self::EXCEPTION_CONTEXT]->getLine()}",
                    'trace'   => $context[self::EXCEPTION_CONTEXT]->getTrace(),
                ];
            }

            return $context;
        }
    }
}
