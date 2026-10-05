<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Actions;

use Brain\Monkey\Functions;
use Cdek\Actions\IntakeCreateAction;
use Cdek\CdekApi;
use Cdek\Exceptions\ShippingNotFoundException;
use Cdek\Loader;
use Cdek\Model\Order;
use Cdek\Note;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use ReflectionProperty;

final class IntakeCreateActionTest extends TestCase
{
    /** Tariff::DOOR_DOOR - забор курьером от двери */
    private const FROM_DOOR_TARIFF = 139;
    /** Tariff::OFFICE_OFFICE - забор курьером невозможен "от двери" */
    private const FROM_OFFICE_TARIFF = 136;

    private const FORM = [
        'call'    => 'true',
        'date'    => '2026-10-10 15:30:00',
        'from'    => '10:00',
        'to'      => '14:00',
        'comment' => 'Позвонить за час',
        'desc'    => 'Одежда',
        'weight'  => '1500',
    ];

    /** @var int[] */
    private array $sleeps = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sleeps = [];

        Functions\when('esc_html__')->returnArg();
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

    private function mockApi(): MockInterface
    {
        return Mockery::mock('overload:' . CdekApi::class);
    }

    private function mockOrder(?object $shipping, ?object $intake = null): MockInterface
    {
        $order         = Mockery::mock('overload:' . Order::class);
        $order->id     = 555;
        $order->number = 'CDEK-777';
        $order->shouldReceive('getShipping')->andReturn($shipping);

        if ($intake !== null) {
            $order->shouldReceive('getIntake')->andReturn($intake);
        }

        return $order;
    }

    private function mockShipping(int $tariff): MockInterface
    {
        $method               = Mockery::mock();
        $method->address      = 'ул. Ленина, 1';
        $method->city_code    = 270;
        $method->seller_name  = 'ООО Продавец';
        $method->seller_phone = '+79990000000';

        $shipping         = Mockery::mock();
        $shipping->tariff = $tariff;
        $shipping->shouldReceive('getMethod')->andReturn($method);

        return $shipping;
    }

    private function mockResponse(?array $error, bool $valid, ?array $entity = null): MockInterface
    {
        $response = Mockery::mock();
        $response->shouldReceive('error')->andReturn($error);
        $response->shouldReceive('missInvalidLegacyRequest')->andReturn($valid);
        $response->shouldReceive('entity')->andReturn($entity);

        return $response;
    }

    private function mockNote(?string $message): void
    {
        $note = Mockery::mock('alias:' . Note::class);

        if ($message === null) {
            $note->shouldReceive('send')->never();

            return;
        }

        $note->shouldReceive('send')->once()->with(555, $message);
    }

    public function testThrowsWhenOrderHasNoShipping(): void
    {
        $this->mockApi()->shouldReceive('intakeCreate')->never();

        $this->expectException(ShippingNotFoundException::class);

        (new IntakeCreateAction())($this->mockOrder(null), self::FORM);
    }

    public function testSendsCdekNumberForIntakeFromDoorTariff(): void
    {
        $captured = null;

        $api = $this->mockApi();
        $api->shouldReceive('intakeCreate')->once()->andReturnUsing(
            function (array $param) use (&$captured) {
                $captured = $param;

                return $this->mockResponse(['code' => 'x', 'message' => 'm'], false);
            },
        );

        (new IntakeCreateAction())($this->mockOrder($this->mockShipping(self::FROM_DOOR_TARIFF)), self::FORM);

        self::assertSame(
            [
                'need_call'        => true,
                'intake_date'      => '2026-10-10',
                'intake_time_from' => '10:00',
                'intake_time_to'   => '14:00',
                'comment'          => 'Позвонить за час',
                'cdek_number'      => 'CDEK-777',
            ],
            $captured,
        );
    }

    public function testSendsSenderDataForIntakeFromOfficeTariff(): void
    {
        $captured = null;

        $api = $this->mockApi();
        $api->shouldReceive('intakeCreate')->once()->andReturnUsing(
            function (array $param) use (&$captured) {
                $captured = $param;

                return $this->mockResponse(['code' => 'x', 'message' => 'm'], false);
            },
        );

        $form = array_merge(self::FORM, ['call' => 'false']);
        unset($form['comment']);

        (new IntakeCreateAction())($this->mockOrder($this->mockShipping(self::FROM_OFFICE_TARIFF)), $form);

        self::assertSame(
            [
                'need_call'        => false,
                'intake_date'      => '2026-10-10',
                'intake_time_from' => '10:00',
                'intake_time_to'   => '14:00',
                'comment'          => null,
                'name'             => 'Одежда',
                'weight'           => '1500',
                'from_location'    => ['address' => 'ул. Ленина, 1', 'code' => 270],
                'sender'           => ['name' => 'ООО Продавец', 'phones' => ['number' => '+79990000000']],
            ],
            $captured,
        );
    }

    public function testReturnsFailureWhenIntakeAlreadyExistsForOrder(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('intakeCreate')->andReturn(
            $this->mockResponse(['code' => 'v2_intake_exists_by_order', 'message' => 'exists'], false),
        );
        $api->shouldReceive('intakeGet')->never();
        $this->mockNote(null);

        $result = (new IntakeCreateAction())($this->mockOrder($this->mockShipping(self::FROM_DOOR_TARIFF)), self::FORM);

        self::assertFalse($result->state());
        self::assertSame('An error occurred while creating intake. Intake for this invoice already exists', $result->message);
        self::assertSame([], $this->sleeps);
    }

    public function testReturnsFailureWithApiMessageWhenRequestIsInvalid(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('intakeCreate')->andReturn(
            $this->mockResponse(['code' => 'v2_invalid_value', 'message' => 'Bad time range'], false),
        );
        $api->shouldReceive('intakeGet')->never();
        $this->mockNote(null);

        $result = (new IntakeCreateAction())($this->mockOrder($this->mockShipping(self::FROM_DOOR_TARIFF)), self::FORM);

        self::assertFalse($result->state());
        self::assertSame('Error. The courier request has not been created. (Bad time range)', $result->message);
        self::assertSame([], $this->sleeps);
    }

    public function testSavesIntakeAndNotesWhenCourierInfoIsReceived(): void
    {
        $intake = Mockery::mock();
        $intake->shouldReceive('save')->once();

        $api = $this->mockApi();
        $api->shouldReceive('intakeCreate')->andReturn($this->mockResponse(null, true, ['uuid' => 'request-uuid']));
        $api->shouldReceive('intakeGet')->once()->with('request-uuid')->andReturn(
            ['uuid' => 'intake-uuid', 'intake_number' => '9001'],
        );
        $this->mockNote('Intake has been created: Number: 9001 | Uuid: intake-uuid');

        $result = (new IntakeCreateAction())(
            $this->mockOrder($this->mockShipping(self::FROM_DOOR_TARIFF), $intake),
            self::FORM,
        );

        self::assertTrue($result->state());
        self::assertSame('', $result->message);
        self::assertSame('intake-uuid', $intake->uuid);
        self::assertSame('9001', $intake->number);
        self::assertSame([5], $this->sleeps);
    }

    public function testReturnsFailureWhenCourierInfoIsNotReceived(): void
    {
        $api = $this->mockApi();
        $api->shouldReceive('intakeCreate')->andReturn($this->mockResponse(null, true, ['uuid' => 'request-uuid']));
        $api->shouldReceive('intakeGet')->once()->with('request-uuid')->andReturn(null);
        $this->mockNote(null);

        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;

            return true;
        }, E_WARNING);

        try {
            $result = (new IntakeCreateAction())($this->mockOrder($this->mockShipping(self::FROM_DOOR_TARIFF)), self::FORM);
        } finally {
            restore_error_handler();
        }

        self::assertFalse($result->state());
        self::assertSame(
            'Intake has been created, but an error occurred while obtaining its number. Intake uuid: ',
            $result->message,
        );

        self::assertNotEmpty($warnings);
        foreach ($warnings as $warning) {
            self::assertStringContainsString('array offset', $warning);
        }
    }

    public function testNewCreatesAction(): void
    {
        $this->mockApi();

        self::assertInstanceOf(IntakeCreateAction::class, IntakeCreateAction::new());
    }
}
