<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Actions;

use Brain\Monkey\Functions;
use Cdek\Actions\IntakeDeleteAction;
use Cdek\CdekApi;
use Cdek\Exceptions\External\InvalidRequestException;
use Cdek\Loader;
use Cdek\Note;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use ReflectionProperty;

final class IntakeDeleteActionTest extends TestCase
{
    private const ORDER_ID = 123;

    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('esc_html__')->returnArg();

        // Loader::getPluginName() нужен конструктору исключений.
        $property = new ReflectionProperty(Loader::class, 'pluginName');
        $property->setAccessible(true);

        if (!$property->isInitialized(null)) {
            $property->setValue(null, 'CDEK');
        }
    }

    private function mockWcOrder(array $courierData, bool $expectClean): MockInterface
    {
        $wcOrder = Mockery::mock('WC_Order');
        $wcOrder->shouldReceive('get_meta')->with('courier_data')->andReturn($courierData);

        if ($expectClean) {
            $wcOrder->shouldReceive('delete_meta_data')->once()->with('courier_data');
            $wcOrder->shouldReceive('save')->once();
        } else {
            $wcOrder->shouldReceive('delete_meta_data')->never();
            $wcOrder->shouldReceive('save')->never();
        }

        Functions\when('wc_get_order')->justReturn($wcOrder);

        return $wcOrder;
    }

    private function mockApi(): MockInterface
    {
        return Mockery::mock('overload:' . CdekApi::class);
    }

    private function mockNote(?string $message): void
    {
        $note = Mockery::mock('alias:' . Note::class);

        if ($message === null) {
            $note->shouldReceive('send')->never();

            return;
        }

        $note->shouldReceive('send')->once()->with(self::ORDER_ID, $message);
    }

    private function invalidRequest(string $code, string $message): InvalidRequestException
    {
        return new InvalidRequestException([['code' => $code, 'message' => $message]]);
    }

    /**
     * @dataProvider incompleteIntakeProvider
     */
    public function testCleansMetaAndReturnsSuccessWithoutApiCallWhenIntakeIsIncomplete(array $courierData): void
    {
        $this->mockWcOrder($courierData, true);

        $api = $this->mockApi();
        $api->shouldReceive('intakeDelete')->never();
        $this->mockNote(null);

        $result = (new IntakeDeleteAction())(self::ORDER_ID);

        self::assertTrue($result->state());
        self::assertSame('Intake is not found in system', $result->message);
    }

    public static function incompleteIntakeProvider(): array
    {
        return [
            'no data'     => [[]],
            'no uuid'     => [['number' => '555']],
            'no number'   => [['uuid' => 'intake-uuid']],
            'empty parts' => [['uuid' => '', 'number' => '']],
        ];
    }

    public function testDeletesIntakeNotesAndCleansMeta(): void
    {
        $this->mockWcOrder(['uuid' => 'intake-uuid', 'number' => '555'], true);

        $api = $this->mockApi();
        $api->shouldReceive('intakeDelete')->once()->with('intake-uuid');
        $this->mockNote('Intake 555 has been deleted');

        $result = (new IntakeDeleteAction())(self::ORDER_ID);

        self::assertTrue($result->state());
        self::assertSame('Intake has been deleted', $result->message);
    }

    public function testTreatsAlreadyRemovedIntakeAsDeleted(): void
    {
        $this->mockWcOrder(['uuid' => 'intake-uuid', 'number' => '555'], true);

        $api = $this->mockApi();
        $api->shouldReceive('intakeDelete')->once()->andThrow(
            $this->invalidRequest('v2_entity_has_final_status', 'Entity has final status REMOVED'),
        );
        $this->mockNote('Intake 555 has been deleted');

        $result = (new IntakeDeleteAction())(self::ORDER_ID);

        self::assertTrue($result->state());
        self::assertSame('Intake has been deleted', $result->message);
    }

    public function testReturnsFailureWhenFinalStatusIsNotRemoved(): void
    {
        $this->mockWcOrder(['uuid' => 'intake-uuid', 'number' => '555'], false);

        $api = $this->mockApi();
        $api->shouldReceive('intakeDelete')->once()->andThrow(
            $this->invalidRequest('v2_entity_has_final_status', 'Entity has final status ACCEPTED'),
        );
        $this->mockNote(null);

        $result = (new IntakeDeleteAction())(self::ORDER_ID);

        self::assertFalse($result->state());
        self::assertSame('Intake has not been deleted. (Entity has final status ACCEPTED)', $result->message);
    }

    public function testReturnsFailureWithApiMessageForOtherErrorCodes(): void
    {
        $this->mockWcOrder(['uuid' => 'intake-uuid', 'number' => '555'], false);

        $api = $this->mockApi();
        $api->shouldReceive('intakeDelete')->once()->andThrow(
            $this->invalidRequest('v2_invalid_value', 'Something REMOVED is wrong'),
        );
        $this->mockNote(null);

        $result = (new IntakeDeleteAction())(self::ORDER_ID);

        self::assertFalse($result->state());
        self::assertSame('Intake has not been deleted. (Something REMOVED is wrong)', $result->message);
    }

    public function testNewCreatesAction(): void
    {
        $this->mockApi();

        self::assertInstanceOf(IntakeDeleteAction::class, IntakeDeleteAction::new());
    }
}
