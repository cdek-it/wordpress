<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Actions;

use Brain\Monkey\Functions;
use Cdek\Actions\GenerateBarcodeAction;
use Cdek\CdekApi;
use Cdek\Config;
use Cdek\Exceptions\External\HttpClientException;
use Cdek\Loader;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use ReflectionProperty;

final class GenerateBarcodeActionTest extends TestCase
{
    private const NUMBER = 'CDEK-777';

    private const CREATE_FAILED_MESSAGE
        = "Failed to create barcode.\nTry re-creating the order.\nYou may need to cancel existing one (if that button exists)";

    private string $originalMaxExecutionTime;

    /** @var int[] */
    private array $sleeps = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalMaxExecutionTime = (string)ini_get('max_execution_time');
        $this->sleeps                   = [];

        Functions\when('esc_html__')->returnArg();
        Functions\when('esc_html')->returnArg();
        Functions\when('sleep')->alias(function (int $seconds): int {
            $this->sleeps[] = $seconds;

            return 0;
        });

        // Loader::getPluginName() нужен конструктору для исключений
        $property = new ReflectionProperty(Loader::class, 'pluginName');
        $property->setAccessible(true);

        if (!$property->isInitialized(null)) {
            $property->setValue(null, 'CDEK');
        }
    }

    protected function tearDown(): void
    {
        ini_set('max_execution_time', $this->originalMaxExecutionTime);

        parent::tearDown();
    }

    private function mockApi(): MockInterface
    {
        return Mockery::mock('overload:' . CdekApi::class);
    }

    /**
     * @param int $formatIndex индекс формата из настроек: 0 = A4, 1 = A5, 2 = A6, 3 = A7
     */
    private function mockBarcodeFormatSetting(int $formatIndex): void
    {
        $methodInstance                 = Mockery::mock();
        $methodInstance->barcode_format = (string)$formatIndex;

        Mockery::mock('alias:' . ShippingMethod::class)
               ->shouldReceive('factory')
               ->andReturn($methodInstance);
    }

    private function orderResponse(?array $entity, array $related = []): MockInterface
    {
        $response = Mockery::mock();
        $response->shouldReceive('entity')->andReturn($entity);
        $response->shouldReceive('related')->andReturn($related);

        return $response;
    }

    private function expectedData(string $raw): string
    {
        return base64_encode($raw);
    }

    public function testRaisesMaxExecutionTimeToCoverPollingWindow(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->andReturn($this->orderResponse(null));

        (new GenerateBarcodeAction())(self::NUMBER);

        self::assertSame(
            (string)(30 + Config::GRAPHICS_FIRST_SLEEP + Config::GRAPHICS_TIMEOUT_SEC * Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS),
            (string)ini_get('max_execution_time'),
        );
    }

    public function testReturnsFailureWhenOrderRequestFails(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->once()->andThrow(new HttpClientException([]));
        $api->shouldReceive('barcodeCreate')->never();

        $result = (new GenerateBarcodeAction())(self::NUMBER);

        self::assertSame(['success' => false, 'message' => self::CREATE_FAILED_MESSAGE], $result);
    }

    public function testReturnsFailureWhenOrderEntityIsMissing(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->once()->with(self::NUMBER)->andReturn($this->orderResponse(null));
        $api->shouldReceive('barcodeCreate')->never();

        $result = (new GenerateBarcodeAction())(self::NUMBER);

        self::assertSame(['success' => false, 'message' => self::CREATE_FAILED_MESSAGE], $result);
        self::assertSame([], $this->sleeps);
    }

    public function testReturnsExistingBarcodeWhenItsFormatMatchesSettings(): void
    {
        $this->mockBarcodeFormatSetting(2); // A6

        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->once()->andReturn($this->orderResponse(
            ['uuid' => 'u'],
            [
                ['type' => 'waybill', 'url' => 'https://cdek.test/waybill.pdf'],
                ['type' => 'barcode', 'url' => 'https://cdek.test/barcode.pdf', 'uuid' => 'barcode-uuid'],
            ],
        ));
        $api->shouldReceive('barcodeGet')->once()->with('barcode-uuid')->andReturn(['format' => 'A6']);
        $api->shouldReceive('fileGetRaw')->once()->with('https://cdek.test/barcode.pdf')->andReturn('BARCODE-PDF');
        $api->shouldReceive('barcodeCreate')->never();

        $result = (new GenerateBarcodeAction())(self::NUMBER);

        self::assertSame(['success' => true, 'data' => $this->expectedData('BARCODE-PDF')], $result);
        self::assertSame([], $this->sleeps);
    }

    /**
     * @dataProvider unsuitableExistingBarcodeProvider
     */
    public function testCreatesNewBarcodeWhenExistingOneIsNotSuitable(array $related, ?array $barcodeGetResult): void
    {
        $this->mockBarcodeFormatSetting(2); // A6

        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->once()->andReturn($this->orderResponse(['uuid' => 'u'], $related));
        // 1-й вызов (если он есть) - проверка существующего, 2-й - опрос нового
        $api->shouldReceive('barcodeGet')->with('barcode-uuid')->andReturn($barcodeGetResult);
        $api->shouldReceive('barcodeCreate')->once()->with(self::NUMBER)->andReturn('new-uuid');
        $api->shouldReceive('barcodeGet')->once()->with('new-uuid')->andReturn(['url' => 'https://cdek.test/new.pdf']);
        $api->shouldReceive('fileGetRaw')->once()->with('https://cdek.test/new.pdf')->andReturn('NEW-PDF');

        $result = (new GenerateBarcodeAction())(self::NUMBER);

        self::assertSame(['success' => true, 'data' => $this->expectedData('NEW-PDF')], $result);
        self::assertSame([Config::GRAPHICS_FIRST_SLEEP], $this->sleeps);
    }

    public static function unsuitableExistingBarcodeProvider(): array
    {
        return [
            'different format'      => [
                [['type' => 'barcode', 'url' => 'https://cdek.test/b.pdf', 'uuid' => 'barcode-uuid']],
                ['format' => 'A4'],
            ],
            'barcode info is null'  => [
                [['type' => 'barcode', 'url' => 'https://cdek.test/b.pdf', 'uuid' => 'barcode-uuid']],
                null,
            ],
            'barcode without uuid'  => [
                [['type' => 'barcode', 'url' => 'https://cdek.test/b.pdf']],
                null,
            ],
            'barcode without url'   => [
                [['type' => 'barcode', 'uuid' => 'barcode-uuid']],
                null,
            ],
            'no related entities'   => [[], null],
        ];
    }

    public function testReturnsFailureWhenBarcodeCreateReturnsNull(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->andReturn($this->orderResponse(['uuid' => 'u']));
        $api->shouldReceive('barcodeCreate')->once()->andReturn(null);
        $api->shouldReceive('barcodeGet')->never();

        $result = (new GenerateBarcodeAction())(self::NUMBER);

        self::assertSame(['success' => false, 'message' => self::CREATE_FAILED_MESSAGE], $result);
        self::assertSame([], $this->sleeps);
    }

    public function testReturnsFailureWhenBarcodeCreateThrowsHttpClientException(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->andReturn($this->orderResponse(['uuid' => 'u']));
        $api->shouldReceive('barcodeCreate')->once()->andThrow(new HttpClientException([]));

        $result = (new GenerateBarcodeAction())(self::NUMBER);

        self::assertSame(['success' => false, 'message' => self::CREATE_FAILED_MESSAGE], $result);
        self::assertSame([], $this->sleeps);
    }

    public function testPollsUntilBarcodeUrlAppears(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->andReturn($this->orderResponse(['uuid' => 'u']));
        $api->shouldReceive('barcodeCreate')->andReturn('new-uuid');
        $api->shouldReceive('barcodeGet')->times(3)->with('new-uuid')->andReturn(
            ['statuses' => [['code' => 'ACCEPTED']]],
            ['statuses' => [['code' => 'PROCESSING']]],
            ['url' => 'https://cdek.test/ready.pdf'],
        );
        $api->shouldReceive('fileGetRaw')->once()->with('https://cdek.test/ready.pdf')->andReturn('READY');

        $result = (new GenerateBarcodeAction())(self::NUMBER);

        self::assertSame(['success' => true, 'data' => $this->expectedData('READY')], $result);
        self::assertSame(
            [Config::GRAPHICS_FIRST_SLEEP, Config::GRAPHICS_TIMEOUT_SEC, Config::GRAPHICS_TIMEOUT_SEC],
            $this->sleeps,
        );
    }

    /**
     * @dataProvider unusableBarcodeInfoProvider
     */
    public function testReturnsTryAgainWhenBarcodeInfoIsUnusable(?array $barcodeInfo): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->andReturn($this->orderResponse(['uuid' => 'u']));
        $api->shouldReceive('barcodeCreate')->andReturn('new-uuid');
        $api->shouldReceive('barcodeGet')->once()->with('new-uuid')->andReturn($barcodeInfo);

        $result = (new GenerateBarcodeAction())(self::NUMBER);

        self::assertSame(['success' => false, 'message' => "Failed to create barcode.\nTry again"], $result);
    }

    public static function unusableBarcodeInfoProvider(): array
    {
        return [
            'null'                 => [null],
            'no statuses'          => [['statuses' => []]],
            'last status INVALID'  => [['statuses' => [['code' => 'ACCEPTED'], ['code' => 'INVALID']]]],
        ];
    }

    public function testReturnsTimeoutMessageWhenRetriesAreExhausted(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->andReturn($this->orderResponse(['uuid' => 'u']));
        $api->shouldReceive('barcodeCreate')->andReturn('new-uuid');
        $api->shouldReceive('barcodeGet')
            ->times(Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS)
            ->andReturn(['statuses' => [['code' => 'PROCESSING']]]);

        $result = (new GenerateBarcodeAction())(self::NUMBER);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Wait for 1 hour before trying again', $result['message']);
        self::assertSame(
            array_merge(
                [Config::GRAPHICS_FIRST_SLEEP],
                array_fill(0, Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS, Config::GRAPHICS_TIMEOUT_SEC),
            ),
            $this->sleeps,
        );
    }

    public function testNewCreatesAction(): void
    {
        self::assertInstanceOf(GenerateBarcodeAction::class, GenerateBarcodeAction::new());
    }
}
