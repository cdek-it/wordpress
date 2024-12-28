<?php

declare(strict_types=1);

namespace {
    defined('ABSPATH') or exit;
}

namespace Cdek\Helpers {
    use WC_Logger_Interface;

    class Logger
    {
        private ?WC_Logger_Interface $logger = null;
        public function __construct()
        {
            $this->logger = wc_get_logger();
        }

        public static function exceptionParser(\Throwable $e): array
        {
            return [
                'message' => $e->getMessage(),
                'file'    => "{$e->getFile()}:{$e->getLine()}",
                'line'    => $e->getLine(),
            ];
        }

        /** @noinspection PhpUnused */
        public static function debug(string $message, array $context = []): void
        {
            if($log = (new static())->logger){
                $log->debug($message, $context);
            }
        }

        /** @noinspection PhpUnused */
        public static function info(string $message, array $context = []): void
        {
            if($log = (new static())->logger){
                $log->info($message, $context);
            }
        }

        /** @noinspection PhpUnused */
        public static function notice(string $message, array $context = []): void
        {
            if($log = (new static())->logger){
                $log->notice($message, $context);
            }
        }

        /** @noinspection PhpUnused */
        public static function warning(string $message, array $context = []): void
        {
            if($log = (new static())->logger){
                $log->warning($message, $context);
            }
        }

        /** @noinspection PhpUnused */
        public static function error(string $message, array $context = []): void
        {
            if($log = (new static())->logger){
                $log->error($message, $context);
            }
        }

        /** @noinspection PhpUnused */
        public static function critical(string $message, array $context = []): void
        {
            if($log = (new static())->logger){
                $log->critical($message, $context);
            }
        }

        /** @noinspection PhpUnused */
        public static function alert(string $message, array $context = []): void
        {
            if($log = (new static())->logger){
                $log->alert($message, $context);
            }
        }

        /** @noinspection PhpUnused */
        public static function emergency(string $message, array $context = []): void
        {
            if($log = (new static())->logger){
                $log->emergency($message, $context);
            }
        }
    }
}
