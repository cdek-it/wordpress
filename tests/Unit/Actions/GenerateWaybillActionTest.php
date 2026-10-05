<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Actions;

use Brain\Monkey\Functions;
use Cdek\Actions\GenerateWaybillAction;
use Cdek\CdekApi;
use Cdek\Config;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;

final class GenerateWaybillActionTest extends TestCase
{
    private const NUMBER = 'CDEK-777';

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

        (new GenerateWaybillAction())(self::NUMBER);

        self::assertSame(
            (string)(30 + Config::GRAPHICS_FIRST_SLEEP + Config::GRAPHICS_TIMEOUT_SEC * Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS),
            (string)ini_get('max_execution_time'),
        );
    }

    public function testReturnsFailureWhenOrderEntityIsMissing(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->once()->with(self::NUMBER)->andReturn($this->orderResponse(null));
        $api->shouldReceive('waybillCreate')->never();

        $result = (new GenerateWaybillAction())(self::NUMBER);

        self::assertFalse($result['success']);
        self::assertStringContainsString('try re-creating the order', $result['message']);
        self::assertSame([], $this->sleeps);
    }

    public function testReturnsExistingWaybillFromRelatedEntitiesWithoutCreatingNew(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->once()->andReturn($this->orderResponse(
            ['uuid' => 'u'],
            [
                ['type' => 'barcode', 'url' => 'https://cdek.test/barcode'],
                ['type' => 'waybill', 'url' => 'https://cdek.test/waybill.pdf'],
            ],
        ));
        $api->shouldReceive('fileGetRaw')->once()->with('https://cdek.test/waybill.pdf')->andReturn('PDF-BYTES');
        $api->shouldReceive('waybillCreate')->never();

        $result = (new GenerateWaybillAction())(self::NUMBER);

        self::assertSame(['success' => true, 'data' => $this->expectedData('PDF-BYTES')], $result);
        self::assertSame([], $this->sleeps);
    }

    public function testCreatesNewWaybillWhenRelatedWaybillHasNoUrl(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->once()->andReturn($this->orderResponse(
            ['uuid' => 'u'],
            [['type' => 'waybill']],
        ));
        $api->shouldReceive('waybillCreate')->once()->with(self::NUMBER)->andReturn('waybill-uuid');
        $api->shouldReceive('waybillGet')->once()->with('waybill-uuid')->andReturn(['url' => 'https://cdek.test/new.pdf']);
        $api->shouldReceive('fileGetRaw')->once()->with('https://cdek.test/new.pdf')->andReturn('NEW-PDF');

        $result = (new GenerateWaybillAction())(self::NUMBER);

        self::assertSame(['success' => true, 'data' => $this->expectedData('NEW-PDF')], $result);
        self::assertSame([Config::GRAPHICS_FIRST_SLEEP], $this->sleeps);
    }

    public function testReturnsFailureWhenWaybillCreateReturnsNull(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->andReturn($this->orderResponse(['uuid' => 'u']));
        $api->shouldReceive('waybillCreate')->once()->andReturn(null);
        $api->shouldReceive('waybillGet')->never();

        $result = (new GenerateWaybillAction())(self::NUMBER);

        self::assertFalse($result['success']);
        self::assertStringContainsString('Try re-creating the order', $result['message']);
        self::assertSame([], $this->sleeps);
    }

    public function testPollsUntilWaybillUrlAppears(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->andReturn($this->orderResponse(['uuid' => 'u']));
        $api->shouldReceive('waybillCreate')->andReturn('waybill-uuid');
        $api->shouldReceive('waybillGet')->times(3)->with('waybill-uuid')->andReturn(
            ['statuses' => [['code' => 'ACCEPTED']]],
            ['statuses' => [['code' => 'PROCESSING']]],
            ['url' => 'https://cdek.test/ready.pdf'],
        );
        $api->shouldReceive('fileGetRaw')->once()->with('https://cdek.test/ready.pdf')->andReturn('READY');

        $result = (new GenerateWaybillAction())(self::NUMBER);

        self::assertSame(['success' => true, 'data' => $this->expectedData('READY')], $result);
        self::assertSame(
            [Config::GRAPHICS_FIRST_SLEEP, Config::GRAPHICS_TIMEOUT_SEC, Config::GRAPHICS_TIMEOUT_SEC],
            $this->sleeps,
        );
    }

    public function testReturnsFailureWhenWaybillInfoIsNull(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->andReturn($this->orderResponse(['uuid' => 'u']));
        $api->shouldReceive('waybillCreate')->andReturn('waybill-uuid');
        $api->shouldReceive('waybillGet')->once()->andReturn(null);

        $result = (new GenerateWaybillAction())(self::NUMBER);

        self::assertFalse($result['success']);
        self::assertSame("Failed to create waybill.\nTry again", $result['message']);
    }

    public function testReturnsFailureWhenLastWaybillStatusIsInvalid(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->andReturn($this->orderResponse(['uuid' => 'u']));
        $api->shouldReceive('waybillCreate')->andReturn('waybill-uuid');
        $api->shouldReceive('waybillGet')->once()->andReturn(
            ['statuses' => [['code' => 'ACCEPTED'], ['code' => 'INVALID']]],
        );

        $result = (new GenerateWaybillAction())(self::NUMBER);

        self::assertFalse($result['success']);
        self::assertSame("Failed to create waybill.\nTry again", $result['message']);
    }

    public function testReturnsTimeoutMessageWhenRetriesAreExhausted(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->andReturn($this->orderResponse(['uuid' => 'u']));
        $api->shouldReceive('waybillCreate')->andReturn('waybill-uuid');
        $api->shouldReceive('waybillGet')
            ->times(Config::MAX_REQUEST_RETRIES_FOR_GRAPHICS)
            ->andReturn(['statuses' => [['code' => 'PROCESSING']]]);

        $result = (new GenerateWaybillAction())(self::NUMBER);

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
        self::assertInstanceOf(GenerateWaybillAction::class, GenerateWaybillAction::new());
    }
}
