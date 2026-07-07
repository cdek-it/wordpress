<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Helpers;

use Brain\Monkey\Functions;
use Cdek\Helpers\WeightConverter;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Mockery;

final class WeightConverterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('absint')->alias(static fn($value): int => abs((int)$value));
    }

    private function mockShippingMethod(bool $packageDefaultToggle, string $weightDefault = '0'): void
    {
        $shippingMethod                                  = Mockery::mock();
        $shippingMethod->product_package_default_toggle = $packageDefaultToggle;
        $shippingMethod->product_weight_default          = $weightDefault;

        Mockery::mock('alias:' . ShippingMethod::class)
            ->shouldReceive('factory')
            ->andReturn($shippingMethod);
    }

    /**
     * @dataProvider supportedMeasurementProvider
     */
    public function testIsSupported(string $measurement, bool $expected): void
    {
        self::assertSame($expected, WeightConverter::isSupported($measurement));
    }

    public static function supportedMeasurementProvider(): array
    {
        return [
            'grams are supported'        => ['g', true],
            'kilograms are supported'    => ['kg', true],
            'pounds are supported'       => ['lbs', true],
            'ounces are supported'       => ['oz', true],
            'tons are not supported'     => ['ton', false],
            'empty string not supported' => ['', false],
        ];
    }

    /**
     * @dataProvider weightInGramsProvider
     */
    public function testGetWeightInGrams(string $unit, float $weight, int $expectedGrams): void
    {
        Functions\when('get_option')->justReturn($unit);
        $this->mockShippingMethod(false);

        self::assertSame($expectedGrams, WeightConverter::getWeightInGrams($weight));
    }

    public static function weightInGramsProvider(): array
    {
        return [
            'grams stay the same, rounded up'      => ['g', 500.4, 501],
            'kilograms are converted to grams'     => ['kg', 2.5, 2500],
            'pounds are converted to grams'        => ['lbs', 2, 908],
            'ounces are converted to grams'        => ['oz', 1, 29],
            'unsupported unit falls back to grams' => ['ton', 12, 12],
        ];
    }

    public function testGetWeightInGramsUsesDefaultWeightWhenGivenWeightIsEmpty(): void
    {
        Functions\when('get_option')->justReturn('kg');
        $this->mockShippingMethod(false, '1,5');

        self::assertSame(1500, WeightConverter::getWeightInGrams(0));
    }

    public function testGetWeightInGramsUsesDefaultWeightWhenPackageDefaultToggleIsEnabled(): void
    {
        Functions\when('get_option')->justReturn('kg');
        $this->mockShippingMethod(true, '2,2');

        self::assertSame(2200, WeightConverter::getWeightInGrams(5));
    }

    /**
     * @dataProvider weightInWcMeasurementProvider
     */
    public function testGetWeightInWcMeasurement(string $unit, float $weight, float $expected): void
    {
        Functions\when('get_option')->justReturn($unit);

        self::assertSame($expected, WeightConverter::getWeightInWcMeasurement($weight));
    }

    public static function weightInWcMeasurementProvider(): array
    {
        return [
            'grams stay the same'                  => ['g', 500.0, 500.0],
            'grams are converted to kilograms'      => ['kg', 2500.0, 2.5],
            'grams are converted to pounds'         => ['lbs', 907.2, 2.0],
            'grams are converted to ounces'         => ['oz', 28.25, 1.0],
            'unsupported unit falls back to grams'  => ['ton', 12.0, 12.0],
        ];
    }

    public function testApplyFallbackReturnsGivenWeightWhenNotEmptyAndDefaultToggleDisabled(): void
    {
        $this->mockShippingMethod(false);

        self::assertSame(3.5, WeightConverter::applyFallback('3.5'));
    }

    public function testApplyFallbackUsesDefaultWeightWhenGivenWeightIsEmpty(): void
    {
        $this->mockShippingMethod(false, '1,5');

        self::assertSame(1.5, WeightConverter::applyFallback(0));
    }

    public function testApplyFallbackUsesDefaultWeightWhenPackageDefaultToggleIsEnabled(): void
    {
        $this->mockShippingMethod(true, '4,2');

        self::assertSame(4.2, WeightConverter::applyFallback(10));
    }
}
