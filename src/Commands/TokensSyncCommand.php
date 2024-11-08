<?php

declare(strict_types=1);

namespace Cdek\Commands;

use Cdek\Helpers\Cache;
use Cdek\Traits\CanBeCreated;
use JsonException;

class TokensSyncCommand {
    use CanBeCreated;
    public function __invoke(array $tokens): void {
        Cache::put([
            'tokens'    => $tokens,
            'endpoints' => array_combine(
                array_keys($tokens),
                array_map(
                    static fn($token) => self::getTokenFooterArray($token)['endpoint'] ?: null,
                    $tokens,
                ),
            ),
        ]);
    }

    public static function getTokenFooterArray(string $token): array
    {
        $arToken = explode('.', $token);

        try {
            return json_decode(
                base64_decode(array_pop($arToken)),
                true,
                512,
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
            );
        } catch (JsonException $e) {
            return [];
        }
    }
}
