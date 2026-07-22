<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Model;

use Brain\Monkey\Functions;
use Cdek\Model\Intake;
use Cdek\Tests\TestCase;
use Mockery;

/**
 * `WC_Order` не имеет рантайм-присутствия (только PHPStan-стаб) - мокается
 * напрямую `Mockery::mock('WC_Order')` без alias:/overload:, см.
 * [[mockery-alias-vs-overload]].
 */
final class IntakeTest extends TestCase
{
    public function testConstructorAcceptsWcOrderInstanceDirectlyAndLoadsMeta(): void
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->once()->with('courier_data')->andReturn(['number' => 'IM123']);

        $intake = new Intake($order);

        self::assertSame('IM123', $intake->number);
    }

    public function testConstructorLooksUpOrderByIdWhenGivenInt(): void
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->once()->with('courier_data')->andReturn(['uuid' => 'im-uuid']);

        Functions\expect('wc_get_order')->once()->with(42)->andReturn($order);

        $intake = new Intake(42);

        self::assertSame('im-uuid', $intake->uuid);
    }

    /**
     * `WC_Order::get_meta()` с `$single = true` (дефолт) возвращает `''`,
     * когда мета-ключа нет - код на это рассчитывает через `?: []`.
     */
    public function testConstructorDefaultsMetaToEmptyArrayWhenGetMetaReturnsFalsyValue(): void
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->once()->with('courier_data')->andReturn('');

        $intake = new Intake($order);

        self::assertNull($intake->number);
        self::assertNull($intake->uuid);
    }

    public function testCleanDeletesMetaAndSavesOrder(): void
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->once()->with('courier_data')->andReturn([]);
        $order->shouldReceive('delete_meta_data')->once()->with('courier_data');
        $order->shouldReceive('save')->once();

        $intake = new Intake($order);
        $intake->clean();

        self::assertTrue(true);
    }

    public function testSaveDeletesThenAddsCurrentMetaAndSavesOrder(): void
    {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->once()->with('courier_data')->andReturn([]);
        $order->shouldReceive('delete_meta_data')->once()->with('courier_data');
        $order->shouldReceive('add_meta_data')->once()->with('courier_data', ['number' => 'IM999'], true);
        $order->shouldReceive('save')->once();

        $intake         = new Intake($order);
        $intake->number = 'IM999';
        $intake->save();

        self::assertTrue(true);
    }

    /**
     * @dataProvider legacyAliasProvider
     */
    public function testGetMigratesLegacyAliasAndPersistsViaSave(
        string $legacyKey,
        string $property,
        string $value
    ): void {
        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_meta')->once()->with('courier_data')->andReturn([$legacyKey => $value]);
        $order->shouldReceive('delete_meta_data')->once()->with('courier_data');
        $order->shouldReceive('add_meta_data')->once()->with('courier_data', [$property => $value], true);
        $order->shouldReceive('save')->once();

        $intake = new Intake($order);

        self::assertSame($value, $intake->$property);
    }

    public static function legacyAliasProvider(): array
    {
        return [
            'number <- courier_number' => ['courier_number', 'number', 'LEGACY-NUM-1'],
            'uuid <- courier_uuid'     => ['courier_uuid', 'uuid', 'legacy-uuid-1'],
        ];
    }
}
