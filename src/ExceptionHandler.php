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
        $error = new WP_Error('cdek_error', 'Error happened at ' . esc_html(Loader::getPluginName()));
        $error->add(
            $e->getKey(),
            esc_html($e->getMessage()),
            self::escapeData($e->getData()),
        );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $error data is pre-escaped above, wp_die() handles WP_Error safely
        wp_die($error, '', $e->getStatusCode());
    }

    private static function escapeData(array $data): array {
        return array_map(
            static fn($value) => is_array($value) ? self::escapeData($value) : esc_html((string) $value),
            $data,
        );
    }
}
