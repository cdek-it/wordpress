<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Actions;

use Brain\Monkey\Functions;
use Cdek\Actions\IntakeDeleteAction;
use Cdek\Actions\OrderDeleteAction;
use Cdek\CdekApi;
use Cdek\Contracts\ExceptionContract;
use Cdek\Exceptions\External\InvalidRequestException;
use Cdek\Loader;
use Cdek\Model\ValidationResult;
use Cdek\Note;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use ReflectionProperty;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class OrderDeleteActionTest extends TestCase
{
    private const ORDER_ID     = 123;
    private const ORDER_NUMBER = 'CDEK-777';

    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('esc_html__')->returnArg();
        Functions\when('wc_get_logger')->justReturn(null);

        $property = new ReflectionProperty(Loader::class, 'pluginName');
        $property->setAccessible(true);
        $property->setValue(null, 'CDEK');
    }

    private function mockWcOrder(): MockInterface
    {
        $wcOrder = Mockery::mock('WC_Order');
        $wcOrder->shouldReceive('get_meta')
                ->with('order_data')
                ->andReturn(['number' => self::ORDER_NUMBER, 'uuid' => 'uuid-1']);
        $wcOrder->shouldReceive('update_meta_data')->once()->with('order_data', []);
        $wcOrder->shouldReceive('save')->once();

        Functions\when('wc_get_order')->justReturn($wcOrder);

        return $wcOrder;
    }

    private function mockApi(): MockInterface
    {
        return Mockery::mock('overload:' . CdekApi::class);
    }

    private function mockNote(string $message): void
    {
        Mockery::mock('alias:' . Note::class)
               ->shouldReceive('send')
               ->once()
               ->with(self::ORDER_ID, $message);
    }

    private function invalidRequest(array $data = []): InvalidRequestException
    {
        $exception = new InvalidRequestException([]);

        $property = new ReflectionProperty(ExceptionContract::class, 'data');
        $property->setAccessible(true);
        $property->setValue($exception, $data);

        return $exception;
    }

    public function testDeletesOrderAndIntakeAndReturnsSuccess(): void
    {
        $this->mockWcOrder();

        $response = Mockery::mock();
        $response->shouldReceive('entity')->once()->andReturn(['uuid' => 'uuid-1']);

        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->once()->with(self::ORDER_NUMBER)->andReturn($response);
        $api->shouldReceive('orderDelete')->once()->with('uuid-1');

        $intakeDelete = new class {
            public array $calls = [];

            public function __invoke(int $orderId): void
            {
                $this->calls[] = $orderId;
            }
        };

        Mockery::mock('alias:' . IntakeDeleteAction::class)
               ->shouldReceive('new')
               ->once()
               ->andReturn($intakeDelete);

        $result = (new OrderDeleteAction())(self::ORDER_ID);

        self::assertInstanceOf(ValidationResult::class, $result);
        self::assertTrue($result->state());
        self::assertSame('Waybill has been deleted', $result->message);
        self::assertSame([self::ORDER_ID], $intakeDelete->calls);
    }

    public function testReturnsFailureAndNotesWhenOrderIsNotFoundInCdek(): void
    {
        $this->mockWcOrder();

        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->once()->andThrow($this->invalidRequest());
        $api->shouldReceive('orderDelete')->never();

        $this->mockNote(
            'An attempt to delete order number ' . self::ORDER_NUMBER . ' failed with an error. Order not found.',
        );

        $result = (new OrderDeleteAction())(self::ORDER_ID);

        self::assertFalse($result->state());
        self::assertSame(
            'An error occurred while deleting the order. Order number ' . self::ORDER_NUMBER . ' was not found',
            $result->message,
        );
    }

    public function testReturnsFailureAndNotesWithErrorCodeWhenCdekRefusesToDelete(): void
    {
        $this->mockWcOrder();

        $response = Mockery::mock();
        $response->shouldReceive('entity')->andReturn(['uuid' => 'uuid-1']);

        $api = $this->mockApi();
        $api->shouldReceive('orderGetByNumber')->once()->andReturn($response);
        $api->shouldReceive('orderDelete')->once()->with('uuid-1')->andThrow(
            $this->invalidRequest([['code' => 'v2_order_not_deletable']]),
        );

        $this->mockNote(
            'An attempt to delete order number ' . self::ORDER_NUMBER
            . ' failed with an error. Error code: v2_order_not_deletable',
        );

        $result = (new OrderDeleteAction())(self::ORDER_ID);

        self::assertFalse($result->state());
        self::assertSame(
            'An error occurred while deleting the order. Order number ' . self::ORDER_NUMBER . ' was not deleted',
            $result->message,
        );
    }
}
