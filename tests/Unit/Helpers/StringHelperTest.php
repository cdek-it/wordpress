<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Helpers;

use Brain\Monkey\Functions;
use Cdek\Helpers\StringHelper;
use Cdek\Tests\TestCase;

final class StringHelperTest extends TestCase
{
    private const CHARSET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function testGenerateRandomDefaultLengthReturnsTenCharacters(): void
    {
        Functions\when('wp_rand')->justReturn(0);

        self::assertSame(10, strlen(StringHelper::generateRandom()));
    }

    /**
     * @dataProvider lengthProvider
     */
    public function testGenerateRandomReturnsStringOfGivenLength(int $length): void
    {
        Functions\when('wp_rand')->justReturn(0);

        self::assertSame($length, strlen(StringHelper::generateRandom($length)));
    }

    public static function lengthProvider(): array
    {
        return [
            'zero length'  => [0],
            'one char'     => [1],
            'usual length' => [5],
            'long string'  => [40],
        ];
    }

    public function testGenerateRandomDoesNotCallWpRandForZeroLength(): void
    {
        Functions\expect('wp_rand')->never();

        self::assertSame('', StringHelper::generateRandom(0));
    }

    public function testGenerateRandomPassesFullCharsetRangeToWpRand(): void
    {
        Functions\expect('wp_rand')
            ->once()
            ->with(0, strlen(self::CHARSET) - 1)
            ->andReturn(0);

        self::assertSame(self::CHARSET[0], StringHelper::generateRandom(1));
    }

    public function testGenerateRandomBuildsStringFromCharsetByWpRandIndex(): void
    {
        Functions\when('wp_rand')->alias(static fn(): int => 5);

        self::assertSame('55555', StringHelper::generateRandom(5));
    }

    public function testGenerateRandomOnlyContainsCharactersFromAllowedCharset(): void
    {
        Functions\when('wp_rand')->alias(static fn(int $min, int $max): int => mt_rand($min, $max));

        self::assertMatchesRegularExpression('/^[0-9a-zA-Z]{100}$/', StringHelper::generateRandom(100));
    }
}
