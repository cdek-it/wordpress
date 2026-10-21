<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Controllers {

    use Brain\Monkey\Functions;
    use Cdek\Actions\IntakeCreateAction;
    use Cdek\Actions\IntakeDeleteAction;
    use Cdek\Blocks\AdminOrderBox;
    use Cdek\Controllers\IntakeController;
    use Cdek\Exceptions\External\InvalidRequestException;
    use Cdek\Exceptions\ShippingNotFoundException;
    use Cdek\Loader;
    use Cdek\MetaKeys;
    use Cdek\Model\Order;
    use Cdek\Model\ValidationResult;
    use Cdek\Tests\TestCase;
    use Closure;
    use Mockery;
    use Mockery\MockInterface;
    use ReflectionProperty;
    use RuntimeException;

    /**
     * @runTestsInSeparateProcesses
     * @preserveGlobalState disabled
     */
    final class IntakeControllerTest extends TestCase
    {
        private const ORDER_ID = 123;
        private const FROM_DOOR_TARIFF = 139;
        private const FROM_OFFICE_TARIFF = 136;

        private const VALID_BODY = [
            'date'    => '2026-10-10',
            'from'    => '10:00',
            'to'      => '14:00',
            'call'    => true,
            'comment' => 'Позвонить за час',
        ];

        /** @var array<int, array> события в порядке появления */
        private array $events = [];

        /** @var object[] созданные "WP_Error" (is_wp_error() опознаёт их по идентичности) */
        private array $wpErrors = [];

        private string $requestBody = '';

        private array $originalServer;
        private array $originalRequest;

        protected function setUp(): void
        {
            parent::setUp();

            $this->events          = [];
            $this->wpErrors        = [];
            $this->requestBody     = json_encode(self::VALID_BODY);
            $this->originalServer  = $_SERVER;
            $this->originalRequest = $_REQUEST;

            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_REQUEST['id']            = (string)self::ORDER_ID;

            $property = new ReflectionProperty(Loader::class, 'pluginName');
            $property->setAccessible(true);
            $property->setValue(null, 'CDEK');

            Functions\when('esc_html__')->returnArg();
            Functions\when('wp_unslash')->returnArg();
            Functions\when('current_user_can')->justReturn(true);
            Functions\when('check_ajax_referer')->alias(function (string $action): int {
                $this->events[] = ['nonce', $action];

                return 1;
            });
            Functions\when('wp_die')->alias(function ($message = '', $status = 0): void {
                $this->events[] = ['die', $message, $status];

                throw new IntakeStopExecution();
            });
            Functions\when('is_wp_error')->alias(
                fn($value): bool => in_array($value, $this->wpErrors, true),
            );
            Functions\when('rest_validate_object_value_from_schema')->alias(
                fn($value, array $schema, string $param) => $this->validateAgainstSchema($value, $schema),
            );

            Mockery::mock('alias:' . AdminOrderBox::class)
                   ->shouldReceive('createOrderMetaBox')
                   ->andReturnUsing(function ($post, array $meta = []): void {
                       $this->events[] = ['metabox', $post, $meta];
                   });
        }

        protected function tearDown(): void
        {
            $_SERVER  = $this->originalServer;
            $_REQUEST = $this->originalRequest;

            parent::tearDown();
        }

        /**
         * @return true|object
         */
        private function validateAgainstSchema($value, array $schema)
        {
            $messages = [];

            foreach ($schema as $key => $rules) {
                if (!is_array($value) || !array_key_exists($key, $value)) {
                    if ($rules['required'] ?? false) {
                        $messages[] = "$key is a required property of intake.";
                    }

                    continue;
                }

                $param = $value[$key];
                $types = [
                    'string'  => 'is_string',
                    'boolean' => 'is_bool',
                    'integer' => 'is_int',
                ];

                if (isset($rules['type']) && !($types[$rules['type']])($param)) {
                    $messages[] = "$key is not of type {$rules['type']}.";

                    continue;
                }

                if (isset($rules['validate_callback']) && !$rules['validate_callback']($param)) {
                    $messages[] = "$key is invalid.";
                }
            }

            if ($messages === []) {
                return true;
            }

            $error = Mockery::mock('WP_Error');
            $error->shouldReceive('get_error_messages')->andReturn($messages);
            $this->wpErrors[] = $error;

            return $error;
        }

        /**
         * @return array<int, array>
         */
        private function execute(callable $handler): array
        {
            Functions\when('file_get_contents')->alias(
                fn(string $path) => $path === 'php://input' ? $this->requestBody : false,
            );

            try {
                $handler();
            } catch (IntakeStopExecution $e) {
                // завершение запроса
            }

            return $this->events;
        }

        private function eventsAfterNonce(array $events): array
        {
            self::assertSame(['nonce', 'official_cdek'], $events[0], 'nonce must be checked first');

            return array_values(array_slice($events, 1));
        }

        private function orderWithTariff(int $tariff): void
        {
            $wcOrder = Mockery::mock('WC_Order');
            $wcOrder->shouldReceive('get_meta')->with('order_data')->andReturn(['tariff_id' => $tariff]);
            $wcOrder->shouldReceive('get_shipping_methods')->andReturn([]);

            Functions\when('wc_get_order')->justReturn($wcOrder);
        }

        private function orderWithShippingTariff(int $shippingTariff, int $orderTariff): void
        {
            $meta = Mockery::mock('WC_Meta_Data');
            $meta->shouldReceive('get_data')->andReturn(
                ['key' => MetaKeys::TARIFF_CODE, 'value' => (string)$shippingTariff],
            );

            $item = Mockery::mock('WC_Order_Item_Shipping');
            $item->shouldReceive('get_method_id')->andReturn('official_cdek');
            $item->shouldReceive('get_data')->andReturn(['instance_id' => 0]);
            $item->shouldReceive('get_meta_data')->andReturn([$meta]);

            $wcOrder = Mockery::mock('WC_Order');
            $wcOrder->shouldReceive('get_meta')->with('order_data')->andReturn(['tariff_id' => $orderTariff]);
            $wcOrder->shouldReceive('get_shipping_methods')->andReturn([$item]);

            Functions\when('wc_get_order')->justReturn($wcOrder);
        }

        private function missingOrder(): void
        {
            Functions\when('wc_get_order')->justReturn(false);
        }

        private function body(array $overrides = [], array $remove = []): string
        {
            $body = array_merge(self::VALID_BODY, $overrides);

            foreach ($remove as $key) {
                unset($body[$key]);
            }

            return json_encode($body);
        }

        private function stubCreateAction(Closure $behavior): object
        {
            $stub = new class($behavior) {
                /** @var array<int, array> */
                public array $calls = [];
                private Closure $behavior;

                public function __construct(Closure $behavior)
                {
                    $this->behavior = $behavior;
                }

                public function __invoke(...$args)
                {
                    $this->calls[] = $args;

                    return ($this->behavior)(...$args);
                }
            };

            Mockery::mock('alias:' . IntakeCreateAction::class)->shouldReceive('new')->andReturn($stub);

            return $stub;
        }

        // ---- create: доступ и входные данные ----

        public function testCreateRejectsNonPostRequests(): void
        {
            $_SERVER['REQUEST_METHOD'] = 'GET';

            $events = $this->eventsAfterNonce($this->execute([IntakeController::class, 'create']));

            self::assertSame([['die', -2, 403]], $events);
        }

        public function testCreateRejectsUsersWithoutEditPermission(): void
        {
            Functions\when('current_user_can')->justReturn(false);

            $events = $this->eventsAfterNonce($this->execute([IntakeController::class, 'create']));

            self::assertSame([['die', -2, 403]], $events);
        }

        public function testCreateShowsErrorWhenOrderIsNotFound(): void
        {
            $this->missingOrder();

            $events = $this->eventsAfterNonce($this->execute([IntakeController::class, 'create']));

            self::assertCount(2, $events);
            self::assertSame('metabox', $events[0][0]);
            self::assertSame(self::ORDER_ID, $events[0][1]);
            self::assertCount(1, $events[0][2]['errors']);
            self::assertSame(['die', '', 0], $events[1]);
        }

        public function testCreateShowsErrorWhenBodyIsNotJson(): void
        {
            $this->orderWithTariff(self::FROM_DOOR_TARIFF);
            $this->requestBody = '{not json';

            $events = $this->eventsAfterNonce($this->execute([IntakeController::class, 'create']));

            self::assertCount(2, $events);
            self::assertInstanceOf(Order::class, $events[0][1]);
            self::assertSame(['errors' => ['Syntax error']], $events[0][2]);
            self::assertSame(['die', '', 0], $events[1]);
        }

        // ---- create: окно ожидания курьера ----

        /**
         * @dataProvider invalidIntakeBodyProvider
         */
        public function testCreateRejectsInvalidCourierWindowWithoutCreatingIntake(
            array $overrides,
            array $remove,
            string $expectedMessage
        ): void {
            $this->orderWithTariff(self::FROM_DOOR_TARIFF);
            $this->requestBody = $this->body($overrides, $remove);
            $action            = $this->stubCreateAction(static function (): void {
                throw new RuntimeException('must not be called for invalid input');
            });

            $events = $this->eventsAfterNonce($this->execute([IntakeController::class, 'create']));

            self::assertSame([], $action->calls);
            self::assertCount(2, $events);
            self::assertInstanceOf(Order::class, $events[0][1]);
            self::assertSame(['errors' => [$expectedMessage]], $events[0][2]);
            self::assertSame(['die', '', 0], $events[1]);
        }

        public static function invalidIntakeBodyProvider(): array
        {
            return [
                'no date'                 => [[], ['date'], 'date is a required property of intake.'],
                'impossible date'         => [['date' => '2026-02-30'], [], 'date is invalid.'],
                'month 13'                => [['date' => '2026-13-01'], [], 'date is invalid.'],
                'from before 9'           => [['from' => '08:59'], [], 'from is invalid.'],
                'from after 19'           => [['from' => '20:00'], [], 'from is invalid.'],
                'from minutes over 59'    => [['from' => '10:60'], [], 'from is invalid.'],
                'to before 12'            => [['to' => '11:59'], [], 'to is invalid.'],
                'to after 22'             => [['to' => '23:00'], [], 'to is invalid.'],
                'no call flag'            => [[], ['call'], 'call is a required property of intake.'],
                'call is not boolean'     => [['call' => 'true'], [], 'call is not of type boolean.'],
            ];
        }

        /**
         * @dataProvider validCourierWindowProvider
         */
        public function testCreateAcceptsBoundaryValuesOfCourierWindow(string $from, string $to): void
        {
            $this->orderWithTariff(self::FROM_DOOR_TARIFF);
            $this->requestBody = $this->body(['from' => $from, 'to' => $to]);
            $action            = $this->stubCreateAction(static fn(): ValidationResult => new ValidationResult(true));

            $this->execute([IntakeController::class, 'create']);

            self::assertCount(1, $action->calls);
        }

        public static function validCourierWindowProvider(): array
        {
            return [
                'earliest window' => ['09:00', '12:00'],
                'latest window'   => ['19:59', '22:59'],
            ];
        }

        // ---- create: доп. поля для забора "со склада" ----

        /**
         * @dataProvider missingOfficeFieldsProvider
         */
        public function testCreateRequiresWeightAndDescriptionForIntakeFromOffice(array $extra, array $expected): void
        {
            $this->orderWithTariff(self::FROM_OFFICE_TARIFF);
            $this->requestBody = json_encode(array_merge(self::VALID_BODY, $extra));
            $action            = $this->stubCreateAction(static function (): void {
                throw new RuntimeException('must not be called');
            });

            $events = $this->eventsAfterNonce($this->execute([IntakeController::class, 'create']));

            self::assertSame([], $action->calls);
            self::assertSame(['errors' => $expected], $events[0][2]);
            self::assertSame(['die', '', 0], $events[1]);
        }

        public static function missingOfficeFieldsProvider(): array
        {
            return [
                'nothing'        => [[], ['weight is a required property of intake.', 'desc is a required property of intake.']],
                'no description' => [['weight' => 1500], ['desc is a required property of intake.']],
                'no weight'      => [['desc' => 'Одежда'], ['weight is a required property of intake.']],
                'weight is text' => [['weight' => '1500', 'desc' => 'x'], ['weight is not of type integer.']],
            ];
        }

        public function testCreateDoesNotRequireWeightAndDescriptionForIntakeFromDoor(): void
        {
            $this->orderWithTariff(self::FROM_DOOR_TARIFF);
            $action = $this->stubCreateAction(static fn(): ValidationResult => new ValidationResult(true));

            $this->execute([IntakeController::class, 'create']);

            self::assertCount(1, $action->calls);
        }

        public function testCreateTakesTariffFromShippingItemBeforeOrderTariff(): void
        {
            $this->orderWithShippingTariff(self::FROM_OFFICE_TARIFF, self::FROM_DOOR_TARIFF);
            $action = $this->stubCreateAction(static fn(): ValidationResult => new ValidationResult(true));

            $events = $this->eventsAfterNonce($this->execute([IntakeController::class, 'create']));

            self::assertSame([], $action->calls);
            self::assertCount(2, $events[0][2]['errors']);
        }

        public function testCreateIgnoresOrderTariffWhenShippingItemSaysFromDoor(): void
        {
            $this->orderWithShippingTariff(self::FROM_DOOR_TARIFF, self::FROM_OFFICE_TARIFF);
            $action = $this->stubCreateAction(static fn(): ValidationResult => new ValidationResult(true));

            $this->execute([IntakeController::class, 'create']);

            self::assertCount(1, $action->calls);
        }

        // ---- create: результат действия ----

        public function testCreateCreatesIntakeAndShowsNoErrorsOnSuccess(): void
        {
            $this->orderWithTariff(self::FROM_DOOR_TARIFF);
            $action = $this->stubCreateAction(static fn(): ValidationResult => new ValidationResult(true));

            $events = $this->eventsAfterNonce($this->execute([IntakeController::class, 'create']));

            self::assertCount(1, $action->calls);
            self::assertInstanceOf(Order::class, $action->calls[0][0]);
            self::assertSame(self::VALID_BODY, $action->calls[0][1]);

            self::assertCount(2, $events);
            self::assertSame($action->calls[0][0], $events[0][1]);
            self::assertSame(['errors' => null], $events[0][2]);
            self::assertSame(['die', '', 0], $events[1]);
        }

        public function testCreateShowsActionMessageWhenIntakeWasNotCreated(): void
        {
            $this->orderWithTariff(self::FROM_DOOR_TARIFF);
            $this->stubCreateAction(
                static fn(): ValidationResult => new ValidationResult(false, 'Intake for this invoice already exists'),
            );

            $events = $this->eventsAfterNonce($this->execute([IntakeController::class, 'create']));

            self::assertSame(['errors' => ['Intake for this invoice already exists']], $events[0][2]);
        }

        public function testCreateListsEveryServerValidationError(): void
        {
            $this->orderWithTariff(self::FROM_DOOR_TARIFF);
            $this->stubCreateAction(static function (): void {
                throw new InvalidRequestException([['message' => 'Bad time range'], ['message' => 'Bad date']]);
            });

            $events = $this->eventsAfterNonce($this->execute([IntakeController::class, 'create']));

            self::assertSame(
                [
                    'errors' => [
                        'Server returned validation error: Bad time range',
                        'Server returned validation error: Bad date',
                    ],
                ],
                $events[0][2],
            );
        }

        public function testCreateShowsServerErrorMessageForOtherPluginExceptions(): void
        {
            $this->orderWithTariff(self::FROM_DOOR_TARIFF);
            $this->stubCreateAction(static function (): void {
                throw new ShippingNotFoundException();
            });

            $events = $this->eventsAfterNonce($this->execute([IntakeController::class, 'create']));

            self::assertCount(1, $events[0][2]['errors']);
            self::assertStringStartsWith('Server returned an error: ', $events[0][2]['errors'][0]);
        }

        // ---- delete ----

        public function testDeleteRejectsNonPostRequests(): void
        {
            $_SERVER['REQUEST_METHOD'] = 'GET';

            $events = $this->eventsAfterNonce($this->execute([IntakeController::class, 'delete']));

            self::assertSame([['die', -2, 403]], $events);
        }

        public function testDeleteRejectsUsersWithoutEditPermission(): void
        {
            Functions\when('current_user_can')->justReturn(false);

            $events = $this->eventsAfterNonce($this->execute([IntakeController::class, 'delete']));

            self::assertSame([['die', -2, 403]], $events);
        }

        /**
         * @dataProvider deleteResultProvider
         */
        public function testDeleteShowsActionResultInMetaBox(ValidationResult $result, string $key): void
        {
            $received = null;
            $stub     = new class(function (int $id) use ($result, &$received): ValidationResult {
                $received = $id;

                return $result;
            }) {
                private Closure $behavior;

                public function __construct(Closure $behavior)
                {
                    $this->behavior = $behavior;
                }

                public function __invoke(int $id): ValidationResult
                {
                    return ($this->behavior)($id);
                }
            };
            Mockery::mock('alias:' . IntakeDeleteAction::class)->shouldReceive('new')->andReturn($stub);

            $events = $this->eventsAfterNonce($this->execute([IntakeController::class, 'delete']));

            self::assertSame(self::ORDER_ID, $received);
            self::assertSame(
                [['metabox', self::ORDER_ID, [$key => [$result->message]]], ['die', '', 0]],
                $events,
            );
        }

        public static function deleteResultProvider(): array
        {
            return [
                'deleted'    => [new ValidationResult(true, 'Intake has been deleted'), 'success'],
                'not deleted' => [new ValidationResult(false, 'Intake has not been deleted. (x)'), 'errors'],
            ];
        }

        // ---- регистрация хуков ----

        public function testInvokeRegistersNothingOutsideAjaxRequests(): void
        {
            Functions\when('wp_doing_ajax')->justReturn(false);
            Functions\expect('add_action')->never();

            (new IntakeController())();

            $this->addToAssertionCount(1);
        }

        public function testInvokeRegistersAjaxHandlersDuringAjaxRequests(): void
        {
            Functions\when('wp_doing_ajax')->justReturn(true);

            $registered = [];
            Functions\when('add_action')->alias(
                function (string $hook, $callback) use (&$registered): bool {
                    $registered[$hook] = $callback;

                    return true;
                },
            );

            (new IntakeController())();

            self::assertSame(
                [
                    'wp_ajax_official_cdek-intake_create' => [IntakeController::class, 'create'],
                    'wp_ajax_official_cdek-intake_delete' => [IntakeController::class, 'delete'],
                ],
                $registered,
            );
        }
    }
}

namespace Cdek\Tests\Unit\Controllers {

    /**
     * Моделирует то, что `wp_die()` не возвращает управление.
     */
    final class IntakeStopExecution extends \RuntimeException
    {
    }
}
