<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Blocks;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Brain\Monkey\Functions;
use Cdek\Blocks\AdminOrderBox;
use Cdek\CdekApi;
use Cdek\Exceptions\External\ApiException;
use Cdek\Exceptions\External\InvalidRequestException;
use Cdek\Helpers\UI;
use Cdek\Loader;
use Cdek\MetaKeys;
use Cdek\Model\Order;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use ReflectionProperty;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AdminOrderBoxTest extends TestCase
{
    private string $pluginDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pluginDir = sys_get_temp_dir() . '/cdek-admin-order-box-' . bin2hex(random_bytes(4));
        mkdir($this->pluginDir . '/templates', 0777, true);

        $templates = [
            'common'     => 'echo "[common]";',
            'create'     => 'echo "[create]";',
            'create_many' => 'echo "[create_many]";',
            'processing' => 'echo "[processing]";',
            'order'      => 'echo "[order:" . json_encode($meta ?? null, JSON_UNESCAPED_UNICODE) . "]";',
        ];

        foreach ($templates as $name => $body) {
            file_put_contents("{$this->pluginDir}/templates/$name.php", "<?php $body\n");
        }

        $this->setLoaderStatic('pluginMainFilePath', $this->pluginDir . '/cdek.php');
        $this->setLoaderStatic('pluginName', 'CDEK Delivery');
        $this->setLoaderStatic('pluginVersion', '1.0.0');

        Functions\when('plugin_dir_path')->alias(static fn(string $file): string => dirname($file) . '/');
        Functions\when('esc_html__')->returnArg();
        Functions\when('esc_html')->alias(static fn($value) => htmlspecialchars((string)$value));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->pluginDir . '/templates/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->pluginDir . '/templates');
        rmdir($this->pluginDir);

        parent::tearDown();
    }

    /**
     * @param mixed $value
     */
    private function setLoaderStatic(string $property, $value): void
    {
        $reflection = new ReflectionProperty(Loader::class, $property);
        $reflection->setAccessible(true);
        $reflection->setValue(null, $value);
    }

    /**
     * @param array<string, mixed> $orderData мета `order_data` заказа
     * @param int|null             $tariff    тариф позиции доставки
     */
    private function wcOrder(array $orderData = [], ?int $tariff = 139, int $instanceId = 7): MockInterface
    {
        $wcOrder = Mockery::mock('WC_Order');
        $wcOrder->shouldReceive('get_meta')->with('order_data')->andReturn($orderData);

        if ($tariff === null) {
            $wcOrder->shouldReceive('get_shipping_methods')->andReturn([]);

            return $wcOrder;
        }

        $meta = Mockery::mock('WC_Meta_Data');
        $meta->shouldReceive('get_data')->andReturn(['key' => MetaKeys::TARIFF_CODE, 'value' => (string)$tariff]);

        $item = Mockery::mock('WC_Order_Item_Shipping');
        $item->shouldReceive('get_method_id')->andReturn('official_cdek');
        $item->shouldReceive('get_data')->andReturn(['instance_id' => $instanceId]);
        $item->shouldReceive('get_meta_data')->andReturn([$meta]);

        $wcOrder->shouldReceive('get_shipping_methods')->andReturn([$item]);

        return $wcOrder;
    }

    private function order(array $orderData = [], ?int $tariff = 139): Order
    {
        return new Order($this->wcOrder($orderData, $tariff));
    }

    private function packagesMode(bool $enabled): void
    {
        $settings                    = Mockery::mock('alias:' . ShippingMethod::class);
        $settings->has_packages_mode = $enabled;
        $settings->shouldReceive('factory')->with(7)->andReturn($settings);
    }

    private function render($post, array $meta = []): string
    {
        ob_start();

        try {
            AdminOrderBox::createOrderMetaBox($post, $meta);
        } finally {
            $output = (string)ob_get_clean();
        }

        return $output;
    }

    /**
     * @param array<int, array{code: string, name?: string, date_time?: string}> $statuses
     */
    private function mockCdekOrder(array $statuses): void
    {
        $response = Mockery::mock();
        $response->shouldReceive('entity')->andReturn(['statuses' => $statuses]);

        Mockery::mock('overload:' . CdekApi::class)->shouldReceive('orderGetByNumber')->andReturn($response);
    }

    private static function status(string $code): array
    {
        return ['code' => $code, 'name' => $code, 'date_time' => '2026-10-05T10:00:00+0000'];
    }

    // ---- createOrderMetaBox ----

    public function testShowsNothingWhenOrderHasNoCdekShipping(): void
    {
        self::assertSame('', $this->render($this->order([], null)));
    }

    public function testAcceptsOrderIdAndLoadsOrderForIt(): void
    {
        Functions\when('wc_get_order')->alias(
            fn(int $id) => $id === 55 ? $this->wcOrder([]) : false,
        );
        $this->packagesMode(false);

        self::assertSame('[common][create]', $this->render(55));
    }

    public function testShowsCreationFormForOrderThatIsNotSentToCdekYet(): void
    {
        $this->packagesMode(false);

        self::assertSame('[common][create]', $this->render($this->order()));
    }

    public function testShowsPackagesFormWhenPackagesModeIsEnabled(): void
    {
        $this->packagesMode(true);

        self::assertSame('[common][create_many]', $this->render($this->order()));
    }

    public function testShowsOrderTemplateWithMetaForOrderAlreadySentToCdek(): void
    {
        $this->mockCdekOrder([self::status('CREATED')]);

        self::assertSame(
            '[common][order:{"success":["saved"]}]',
            $this->render($this->order(['number' => '777']), ['success' => ['saved']]),
        );
    }

    public function testReplacesMetaErrorsWithServerValidationErrorsFromStatusLoading(): void
    {
        Mockery::mock('overload:' . CdekApi::class)
               ->shouldReceive('orderGetByNumber')
               ->andThrow(new InvalidRequestException([['message' => 'Bad <b>request</b>'], ['message' => 'Second']]));

        self::assertSame(
            '[common][order:{"errors":["Server returned validation error: Bad &lt;b&gt;request&lt;\/b&gt;",'
            . '"Server returned validation error: Second"]}]',
            $this->render($this->order(['number' => '777']), ['errors' => ['old error']]),
        );
    }

    public function testStillShowsOrderTemplateWhenStatusLoadingFailsForOtherReasons(): void
    {
        Mockery::mock('overload:' . CdekApi::class)
               ->shouldReceive('orderGetByNumber')
               ->andThrow(new ApiException([]));

        self::assertSame(
            '[common][order:{"errors":["kept"]}]',
            $this->render($this->order(['number' => '777']), ['errors' => ['kept']]),
        );
    }

    public function testShowsProcessingTemplateWhenOrderNumberIsStillEmpty(): void
    {
        self::assertSame('[common][processing]', $this->render($this->order(['number' => ''])));
    }

    // ---- noAuthMetaBox ----

    public function testNoAuthMetaBoxLinksToPluginSettingsAndEscapesPluginName(): void
    {
        $this->setLoaderStatic('pluginName', 'CDEK <Delivery>');
        Functions\when('admin_url')->alias(static fn(string $path): string => 'https://shop.test/wp-admin/' . $path);
        Functions\when('esc_url')->returnArg();

        ob_start();
        AdminOrderBox::noAuthMetaBox();
        $output = (string)ob_get_clean();

        self::assertStringContainsString('<h4>Authorization failed</h4>', $output);
        self::assertStringContainsString(
            '<a href="https://shop.test/wp-admin/admin.php?page=wc-settings&tab=shipping&section=official_cdek">',
            $output,
        );
        self::assertStringContainsString('CDEK &lt;Delivery&gt;', $output);
        self::assertStringNotContainsString('<Delivery>', $output);
    }

    // ---- registerMetaBoxes ----

    /** @var array<int, array> */
    private array $metaBoxes = [];

    /** @var array<int, array> */
    private array $actions = [];

    private function captureWordPressRegistrations(): void
    {
        $this->metaBoxes = [];
        $this->actions   = [];

        Functions\when('add_meta_box')->alias(function (...$args): void {
            $this->metaBoxes[] = $args;
        });
        Functions\when('add_action')->alias(function (...$args): bool {
            $this->actions[] = $args;

            return true;
        });
    }

    private function mockOrderType(bool $isOrder): void
    {
        Functions\when('wc_get_order_types')->justReturn(['shop_order']);

        Mockery::mock('alias:' . OrderUtil::class)->shouldReceive('is_order')->andReturn($isOrder);
    }

    public function testRegisterMetaBoxesIgnoresEmptyPost(): void
    {
        $this->captureWordPressRegistrations();

        AdminOrderBox::registerMetaBoxes('shop_order', null);

        self::assertSame([], $this->metaBoxes);
        self::assertSame([], $this->actions);
    }

    public function testRegisterMetaBoxesIgnoresPostsThatAreNotOrders(): void
    {
        $this->captureWordPressRegistrations();
        $this->mockOrderType(false);

        AdminOrderBox::registerMetaBoxes('post', $this->wcOrder());

        self::assertSame([], $this->metaBoxes);
        self::assertSame([], $this->actions);
    }

    public function testRegisterMetaBoxesIgnoresOrdersWithoutCdekShipping(): void
    {
        $this->captureWordPressRegistrations();
        $this->mockOrderType(true);

        AdminOrderBox::registerMetaBoxes('shop_order', $this->wcOrder([], null));

        self::assertSame([], $this->metaBoxes);
        self::assertSame([], $this->actions);
    }

    public function testRegisterMetaBoxesIgnoresShippingWithoutTariff(): void
    {
        $this->captureWordPressRegistrations();
        $this->mockOrderType(true);

        $meta = Mockery::mock('WC_Meta_Data');
        $meta->shouldReceive('get_data')->andReturn(['key' => '_unrelated', 'value' => 'x']);
        $item = Mockery::mock('WC_Order_Item_Shipping');
        $item->shouldReceive('get_method_id')->andReturn('official_cdek');
        $item->shouldReceive('get_data')->andReturn(['instance_id' => 7]);
        $item->shouldReceive('get_meta_data')->andReturn([$meta]);
        $wcOrder = Mockery::mock('WC_Order');
        $wcOrder->shouldReceive('get_meta')->andReturn([]);
        $wcOrder->shouldReceive('get_shipping_methods')->andReturn([$item]);

        AdminOrderBox::registerMetaBoxes('shop_order', $wcOrder);

        self::assertSame([], $this->metaBoxes);
        self::assertSame([], $this->actions);
    }

    public function testRegisterMetaBoxesAddsOrderBoxWhenAuthorizationIsValid(): void
    {
        $this->captureWordPressRegistrations();
        $this->mockOrderType(true);

        $authInstance = null;
        $api          = Mockery::mock('overload:' . CdekApi::class);
        $api->shouldReceive('__construct')->andReturnUsing(
            static function ($method = null) use (&$authInstance): void {
                $authInstance = $method;
            },
        );
        $api->shouldReceive('authGetError')->andReturn(null);

        AdminOrderBox::registerMetaBoxes('shop_order', $this->wcOrder());

        self::assertSame(7, $authInstance);
        self::assertSame([['admin_enqueue_scripts', [AdminOrderBox::class, 'registerOrderScripts']]], $this->actions);
        self::assertSame(
            [
                [
                    'official_cdek_order',
                    'CDEK Delivery',
                    [AdminOrderBox::class, 'createOrderMetaBox'],
                    ['woocommerce_page_wc-orders', 'shop_order'],
                    'side',
                    'core',
                ],
            ],
            $this->metaBoxes,
        );
    }

    public function testRegisterMetaBoxesAddsAuthorizationWarningWhenTokenCannotBeFetched(): void
    {
        $this->captureWordPressRegistrations();
        $this->mockOrderType(true);

        Mockery::mock('overload:' . CdekApi::class)->shouldReceive('authGetError')->andReturn('invalid_client');

        AdminOrderBox::registerMetaBoxes('shop_order', $this->wcOrder());

        self::assertCount(1, $this->metaBoxes);
        self::assertSame([AdminOrderBox::class, 'noAuthMetaBox'], $this->metaBoxes[0][2]);
        self::assertSame([['admin_enqueue_scripts', [AdminOrderBox::class, 'registerOrderScripts']]], $this->actions);
    }

    // ---- скрипты и регистрация ----

    public function testRegisterOrderScriptsEnqueuesCreateOrderScriptWithStylesAndNonce(): void
    {
        $calls = [];
        Mockery::mock('alias:' . UI::class)->shouldReceive('enqueueScript')->andReturnUsing(
            static function (...$args) use (&$calls): void {
                $calls[] = $args;
            },
        );

        AdminOrderBox::registerOrderScripts();

        self::assertSame([['cdek-admin-create-order', 'cdek-create-order', true, false, true]], $calls);
    }

    public function testInvokeHooksMetaBoxRegistrationIntoAddMetaBoxes(): void
    {
        $this->captureWordPressRegistrations();

        (new AdminOrderBox())();

        self::assertSame(
            [['add_meta_boxes', [AdminOrderBox::class, 'registerMetaBoxes'], 100, 2]],
            $this->actions,
        );
    }
}
