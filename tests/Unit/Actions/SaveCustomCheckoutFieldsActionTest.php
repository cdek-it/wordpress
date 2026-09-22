<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Actions;

use Brain\Monkey\Functions;
use Cdek\Actions\SaveCustomCheckoutFieldsAction;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;

final class SaveCustomCheckoutFieldsActionTest extends TestCase
{
    private const FIELDS = [
        'passport_series',
        'passport_number',
        'passport_date_of_issue',
        'passport_organization',
        'tin',
        'passport_date_of_birth',
    ];

    private array $originalPost;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalPost = $_POST;
        $_POST               = [];

        // По умолчанию - тождественная функция, чтобы в тестах, которые не проверяют
        // именно санитайзинг, можно было сравнивать с исходным "сырым" значением.
        Functions\when('sanitize_text_field')->returnArg();
    }

    protected function tearDown(): void
    {
        $_POST = $this->originalPost;

        parent::tearDown();
    }

    private function mockOrder(): MockInterface
    {
        return Mockery::mock('WC_Order');
    }

    /**
     * @dataProvider fieldProvider
     */
    public function testInvokeUpdatesMetaDataForEachSupportedPostField(string $field): void
    {
        $_POST[$field] = 'value-' . $field;

        $order = $this->mockOrder();
        $order->shouldReceive('update_meta_data')->once()->with("_$field", 'value-' . $field);

        (new SaveCustomCheckoutFieldsAction())($order, []);

        self::assertTrue(true);
    }

    public static function fieldProvider(): array
    {
        return array_map(static fn(string $field) => [$field], self::FIELDS);
    }

    public function testInvokeSkipsUpdateWhenPostIsEmpty(): void
    {
        $order = $this->mockOrder();
        $order->shouldNotReceive('update_meta_data');

        (new SaveCustomCheckoutFieldsAction())($order, []);

        self::assertTrue(true);
    }

    public function testInvokeIgnoresPostKeysOutsideAllowList(): void
    {
        $_POST['unrelated_field'] = 'should-be-ignored';

        $order = $this->mockOrder();
        $order->shouldNotReceive('update_meta_data');

        (new SaveCustomCheckoutFieldsAction())($order, []);

        self::assertTrue(true);
    }

    public function testInvokeUpdatesAllFieldsPresentInPostInOnePass(): void
    {
        foreach (self::FIELDS as $field) {
            $_POST[$field] = "raw-$field";
        }

        $order = $this->mockOrder();

        foreach (self::FIELDS as $field) {
            $order->shouldReceive('update_meta_data')->once()->with("_$field", "raw-$field");
        }

        (new SaveCustomCheckoutFieldsAction())($order, []);

        self::assertTrue(true);
    }

    public function testInvokeSanitizesPostValueBeforeStoringInMeta(): void
    {
        Functions\when('sanitize_text_field')->alias(static fn($value) => 'sanitized:' . $value);

        $_POST['tin'] = 'raw-tin-value';

        $order = $this->mockOrder();
        $order->shouldReceive('update_meta_data')->once()->with('_tin', 'sanitized:raw-tin-value');

        (new SaveCustomCheckoutFieldsAction())($order, []);

        self::assertTrue(true);
    }
}
