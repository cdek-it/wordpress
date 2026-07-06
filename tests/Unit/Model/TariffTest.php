<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Model;

use Brain\Monkey\Functions;
use Cdek\Model\Tariff;
use Cdek\Tests\TestCase;
use RuntimeException;

final class TariffTest extends TestCase
{
    public function testGetTypeReturnsShopTypeForDoorToDoorTariff(): void
    {
        self::assertSame(Tariff::SHOP_TYPE, Tariff::getType(139));
    }

    public function testGetTypeReturnsDeliveryTypeForCargoTariff(): void
    {
        self::assertSame(Tariff::DELIVERY_TYPE, Tariff::getType(748));
    }

    public function testGetTypeThrowsForUnknownCode(): void
    {
        Functions\when('esc_html')->returnArg();

        $this->expectException(RuntimeException::class);

        Tariff::getType(999999);
    }

    /**
     * @dataProvider toOfficeProvider
     */
    public function testIsToOffice(int $code, bool $expected): void
    {
        self::assertSame($expected, Tariff::isToOffice($code));
    }

    public static function toOfficeProvider(): array
    {
        return [
            'door-to-office is to office'     => [138, true],
            'office-to-office is to office'   => [136, true],
            'door-to-pickup is to office'     => [366, true],
            'door-to-door is not to office'   => [139, false],
            'office-to-door is not to office' => [137, false],
        ];
    }

    /**
     * @dataProvider fromDoorProvider
     */
    public function testIsFromDoor(int $code, bool $expected): void
    {
        self::assertSame($expected, Tariff::isFromDoor($code));
    }

    public static function fromDoorProvider(): array
    {
        return [
            'door-to-door is from door'     => [139, true],
            'door-to-office is from door'   => [138, true],
            'door-to-pickup is from door'   => [366, true],
            'office-to-door is not door'    => [137, false],
            'office-to-office is not door'  => [136, false],
        ];
    }

    public function testAvailableForShopsIsTrueForShopTariff(): void
    {
        self::assertTrue(Tariff::availableForShops(139));
    }

    public function testAvailableForShopsIsFalseForCargoTariff(): void
    {
        self::assertFalse(Tariff::availableForShops(748));
    }

    public function testListReturnsAllTariffsKeyedByCode(): void
    {
        $list = Tariff::list();

        self::assertArrayHasKey(139, $list);
        self::assertSame('Посылка дверь-дверь (139)', $list[139]);
    }
}
