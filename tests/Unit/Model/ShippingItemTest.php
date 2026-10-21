<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Model;

use Cdek\Config;
use Cdek\MetaKeys;
use Cdek\Model\ShippingItem;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ShippingItemTest extends TestCase
{
    private function mockMetaDatum(string $key, $value): MockInterface
    {
        $meta = Mockery::mock('WC_Meta_Data');
        $meta->shouldReceive('get_data')->andReturn(['key' => $key, 'value' => $value]);

        return $meta;
    }

    private function mockWcItem(
        array $metaData = [],
        int $instanceId = 5,
        string $methodId = Config::DELIVERY_NAME
    ): MockInterface {
        $item = Mockery::mock('WC_Order_Item_Shipping');
        $item->shouldReceive('get_method_id')->andReturn($methodId);
        $item->shouldReceive('get_data')->andReturn(['instance_id' => $instanceId]);
        $item->shouldReceive('get_meta_data')->andReturn($metaData);

        return $item;
    }

    public function testConstructorThrowsWhenMethodIdIsNotPluginDeliveryName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $item = Mockery::mock('WC_Order_Item_Shipping');
        $item->shouldReceive('get_method_id')->andReturn('flat_rate');

        new ShippingItem($item);
    }

    public function testGetInstanceIdReturnsInstanceIdFromWcItemData(): void
    {
        $wcItem = $this->mockWcItem([], 7);

        $item = new ShippingItem($wcItem);

        self::assertSame(7, $item->getInstanceId());
    }

    public function testMagicGetMapsPublicPropertyNameToInternalMetaKey(): void
    {
        $wcItem = $this->mockWcItem([$this->mockMetaDatum(MetaKeys::TARIFF_CODE, '139')]);

        $item = new ShippingItem($wcItem);

        self::assertSame('139', $item->tariff);
    }

    public function testMagicGetReturnsNullForUnmappedKeyWithoutAlias(): void
    {
        $wcItem = $this->mockWcItem([]);

        $item = new ShippingItem($wcItem);

        self::assertNull($item->unknown_property);
    }

    public function testMagicSetOnMappedPropertyIsPersistedUnderInternalMetaKey(): void
    {
        $wcItem = $this->mockWcItem([]);
        $wcItem->shouldReceive('add_meta_data')->once()->with(MetaKeys::TARIFF_CODE, '139', true);
        $wcItem->shouldReceive('save')->once();

        $item         = new ShippingItem($wcItem);
        $item->tariff = '139';
        $item->save();

        self::assertSame('139', $item->tariff);
    }

    public function testSaveAddsAllDirtyMetaAndClearsDirtyList(): void
    {
        $wcItem = $this->mockWcItem([]);
        $wcItem->shouldReceive('add_meta_data')->once()->with(MetaKeys::LENGTH, '10', true);
        $wcItem->shouldReceive('add_meta_data')->once()->with(MetaKeys::WEIGHT, '500', true);
        $wcItem->shouldReceive('save')->twice();

        $item         = new ShippingItem($wcItem);
        $item->length = '10';
        $item->weight = '500';
        $item->save();

        // dirty list was cleared, so a second save() must not add meta again
        $item->save();

        self::assertTrue(true);
    }

    public function testGetMethodReturnsShippingMethodFactoryResultForInstanceId(): void
    {
        $wcItem = $this->mockWcItem([], 9);

        $shippingMethod = Mockery::mock('alias:' . ShippingMethod::class);
        $shippingMethod->shouldReceive('factory')->once()->with(9)->andReturn($shippingMethod);

        $item = new ShippingItem($wcItem);

        self::assertSame($shippingMethod, $item->getMethod());
    }

    public function testUpdateNameCallsSetNameOnOriginalItem(): void
    {
        $wcItem = $this->mockWcItem([]);
        $wcItem->shouldReceive('set_name')->once()->with('CDEK Shipping');

        $item = new ShippingItem($wcItem);
        $item->updateName('CDEK Shipping');

        self::assertTrue(true);
    }

    public function testUpdateTotalCallsSetTotalOnOriginalItem(): void
    {
        $wcItem = $this->mockWcItem([]);
        $wcItem->shouldReceive('set_total')->once()->with(199.99);

        $item = new ShippingItem($wcItem);
        $item->updateTotal(199.99);

        self::assertTrue(true);
    }

    public function testCleanDoesNothing(): void
    {
        $wcItem = $this->mockWcItem([$this->mockMetaDatum(MetaKeys::TARIFF_CODE, '139')]);

        $item = new ShippingItem($wcItem);
        $item->clean();

        self::assertSame('139', $item->tariff);
    }
}
