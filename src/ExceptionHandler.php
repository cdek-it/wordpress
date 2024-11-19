<?php

declare(strict_types=1);

namespace Cdek;

use Cdek\Contracts\ExceptionContract;
use Cdek\Traits\CanBeCreated;
use Throwable;
use WP_Error;

class ExceptionHandler {

    use CanBeCreated;
    /**
     * @var ?callable
     */
    private static $handler;
    public function __invoke(): void {
        self::$handler = set_exception_handler([__CLASS__, 'handle']);
    }

    /**
     * @throws Throwable
     */
    public static function handle(Throwable $e): void {
        if (!$e instanceof ExceptionContract) {
            if (self::$handler === null) {
                throw $e;
            }

            call_user_func(self::$handler, $e);
        }

        // WP_Error при выводе на экран съедает часть data 0 ошибки, поэтому оригинальную ошибку добавляем 1
        $error = new WP_Error('cdek_error', 'Error happened at CDEKDelivery');
        $error->add($e->getKey(), $e->getMessage(), $e->getData());

        wp_die($error, '', $e->getStatusCode());
    }
}
