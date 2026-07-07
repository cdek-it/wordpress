<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Enums;

use Cdek\Enums\BarcodeFormat;
use Cdek\Tests\TestCase;
use RuntimeException;

final class BarcodeFormatTest extends TestCase
{
    /**
     * @dataProvider availableValueProvider
     */
    public function testConstructorAcceptsAvailableValue(string $value): void
    {
        self::assertSame($value, (string)new BarcodeFormat($value));
    }

    public static function availableValueProvider(): array
    {
        return [
            'A4' => ['A4'],
            'A5' => ['A5'],
            'A6' => ['A6'],
            'A7' => ['A7'],
        ];
    }

    public function testConstructorThrowsForUnsupportedValue(): void
    {
        $this->expectException(RuntimeException::class);

        new BarcodeFormat('A3');
    }

    public function testConstructorThrowsForEmptyValue(): void
    {
        $this->expectException(RuntimeException::class);

        new BarcodeFormat('');
    }

    public function testGetAllReturnsAllAvailableValues(): void
    {
        self::assertSame(['A4', 'A5', 'A6', 'A7'], BarcodeFormat::getAll());
    }

    /**
     * @dataProvider indexProvider
     */
    public function testGetByIndexReturnsValueAtGivenIndex(int $index, string $expected): void
    {
        self::assertSame($expected, BarcodeFormat::getByIndex($index));
    }

    public static function indexProvider(): array
    {
        return [
            [0, 'A4'],
            [1, 'A5'],
            [2, 'A6'],
            [3, 'A7'],
        ];
    }
}
