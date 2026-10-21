<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\UI;

use Brain\Monkey\Functions;
use Cdek\Helpers\UI;
use Cdek\MetaKeys;
use Cdek\Tests\TestCase;
use Cdek\UI\AdminOrderProductFields;
use Mockery;
use Mockery\MockInterface;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AdminOrderProductFieldsTest extends TestCase
{
    private const ITEM_ID = 42;

    /** @var array<int, array> параметры вызовов woocommerce_wp_text_input() */
    private array $textInputs = [];

    /** @var array<int, array> параметры вызовов UI::enqueueScript() */
    private array $scripts = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->textInputs = [];
        $this->scripts    = [];

        Functions\when('wc_get_logger')->justReturn(null);

        Functions\when('__')->returnArg();
        Functions\when('esc_attr')->alias(static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES));
        Functions\when('wc_render_action_buttons')->alias(
            static fn(array $buttons): string => '[button:' . $buttons[0]['action'] . '|' . $buttons[0]['name'] . ']',
        );
        Functions\when('woocommerce_wp_text_input')->alias(function (array $args): void {
            $this->textInputs[] = $args;

            echo '[input:' . $args['id'] . '=' . ($args['value'] ?? '') . ']';
        });

        Mockery::mock('alias:' . UI::class)->shouldReceive('enqueueScript')->andReturnUsing(
            function (...$args): void {
                $this->scripts[] = $args;
            },
        );
    }

    /**
     * @param string[] $methodIds методы доставки заказа
     */
    private function itemOfOrderWithShipping(array $methodIds): MockInterface
    {
        $methods = array_map(
            static function (string $id): MockInterface {
                $method = Mockery::mock();
                $method->shouldReceive('get_method_id')->andReturn($id);

                return $method;
            },
            $methodIds,
        );

        $order = Mockery::mock('WC_Order');
        $order->shouldReceive('get_shipping_methods')->andReturn($methods);

        $item = Mockery::mock('WC_Order_Item');
        $item->shouldReceive('get_order')->andReturn($order);

        return $item;
    }

    private function render(?object $item): string
    {
        ob_start();

        try {
            (new AdminOrderProductFields())(self::ITEM_ID, $item, null);
        } finally {
            $output = (string)ob_get_clean();
        }

        return $output;
    }

    private function storedUin($value): void
    {
        Functions\when('wc_get_order_item_meta')->alias(
            static fn(int $id, string $key) => $id === self::ITEM_ID && $key === MetaKeys::JEWEL_UIN ? $value : null,
        );
    }

    // ---- когда поле не показывается ----

    public function testShowsNothingWithoutItem(): void
    {
        self::assertSame('', $this->render(null));
        self::assertSame([], $this->scripts);
    }

    /**
     * @dataProvider unsuitableOrderProvider
     */
    public function testShowsNothingWhenItemHasNoWooCommerceOrder($order): void
    {
        $item = Mockery::mock('WC_Order_Item');
        $item->shouldReceive('get_order')->andReturn($order);

        self::assertSame('', $this->render($item));
        self::assertSame([], $this->scripts);
    }

    public static function unsuitableOrderProvider(): array
    {
        return [
            'order not found' => [false],
            'not an order'    => [new \stdClass()],
        ];
    }

    public function testShowsNothingWhenOrderIsNotShippedByCdek(): void
    {
        $this->storedUin('');

        self::assertSame('', $this->render($this->itemOfOrderWithShipping(['flat_rate', 'local_pickup'])));
        self::assertSame([], $this->textInputs);
        self::assertSame([], $this->scripts);
    }

    // ---- поле UIN ----

    public function testOffersToAddUinWhenItIsNotSetYet(): void
    {
        $this->storedUin('');

        $output = $this->render($this->itemOfOrderWithShipping(['official_cdek']));

        self::assertSame(
            '<br/>'
            . '[button:official_cdek-show_uin|Add jewel UIN]'
            . '<div class="official_cdek-uin-input-container hidden" data-id="42">'
            . '[input:official_cdek_jewel_uin_42=]'
            . '[button:official_cdek-save_uin|Save]'
            . '</div>',
            $output,
        );
    }

    /**
     * @dataProvider emptyUinProvider
     */
    public function testTreatsEveryEmptyValueAsMissingUin($stored): void
    {
        $this->storedUin($stored);

        $output = $this->render($this->itemOfOrderWithShipping(['official_cdek']));

        self::assertStringContainsString('[button:official_cdek-show_uin|Add jewel UIN]', $output);
        self::assertStringContainsString('uin-input-container hidden"', $output);
    }

    public static function emptyUinProvider(): array
    {
        return [
            'empty string' => [''],
            'null'         => [null],
            'false'        => [false],
            'zero string'  => ['0'],
        ];
    }

    public function testShowsSavedUinWithoutAddButton(): void
    {
        $this->storedUin('J-12345');

        $output = $this->render($this->itemOfOrderWithShipping(['official_cdek']));

        self::assertSame(
            '<br/>'
            . '<div class="official_cdek-uin-input-container" data-id="42">'
            . '[input:official_cdek_jewel_uin_42=J-12345]'
            . '[button:official_cdek-save_uin|Save]'
            . '</div>',
            $output,
        );
    }

    public function testTextInputReceivesIdClassLabelAndValue(): void
    {
        $this->storedUin('J-12345');

        $this->render($this->itemOfOrderWithShipping(['official_cdek']));

        self::assertSame(
            [
                [
                    'id'    => 'official_cdek_jewel_uin_42',
                    'class' => 'official_cdek-jewel-uin',
                    'label' => 'UIN: ',
                    'value' => 'J-12345',
                ],
            ],
            $this->textInputs,
        );
    }

    public function testRecognizesCdekShippingAmongSeveralMethodsAndByMethodIdPrefix(): void
    {
        $this->storedUin('J-1');

        $output = $this->render($this->itemOfOrderWithShipping(['flat_rate', 'official_cdek:3']));

        self::assertStringContainsString('[input:official_cdek_jewel_uin_42=J-1]', $output);
    }

    public function testTreatsFailedUinLookupAsMissingUin(): void
    {
        Functions\when('wc_get_order_item_meta')->alias(static function (): void {
            throw new \Exception('database is gone');
        });

        $output = $this->render($this->itemOfOrderWithShipping(['official_cdek']));

        self::assertStringContainsString('[button:official_cdek-show_uin|Add jewel UIN]', $output);
        self::assertStringContainsString('uin-input-container hidden"', $output);
        self::assertSame('', $this->textInputs[0]['value'] ?? '');
    }

    public function testEscapesItemIdInContainerAttribute(): void
    {
        $this->storedUin('');
        $escaped = [];
        Functions\when('esc_attr')->alias(function ($value) use (&$escaped): string {
            $escaped[] = $value;

            return (string)$value;
        });

        $this->render($this->itemOfOrderWithShipping(['official_cdek']));

        self::assertSame(['official_cdek', self::ITEM_ID], $escaped);
    }

    public function testEnqueuesOrderItemScriptWithStylesAndNonce(): void
    {
        $this->storedUin('');

        $this->render($this->itemOfOrderWithShipping(['official_cdek']));

        self::assertSame([['cdek-order-item', 'cdek-order-item', true, false, true]], $this->scripts);
    }
}
