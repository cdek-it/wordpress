<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Controllers {

    use Brain\Monkey\Functions;
    use Cdek\Actions\GenerateBarcodeAction;
    use Cdek\Actions\GenerateWaybillAction;
    use Cdek\Actions\OrderCreateAction;
    use Cdek\Actions\OrderDeleteAction;
    use Cdek\Blocks\AdminOrderBox;
    use Cdek\Controllers\OrderController;
    use Cdek\Exceptions\External\InvalidRequestException;
    use Cdek\Exceptions\ShippingNotFoundException;
    use Cdek\Loader;
    use Cdek\Model\Order;
    use Cdek\Model\ValidationResult;
    use Cdek\Tests\TestCase;
    use Closure;
    use Mockery;
    use ReflectionProperty;
    use RuntimeException;

    /**
     * @runTestsInSeparateProcesses
     * @preserveGlobalState disabled
     */
    final class OrderControllerTest extends TestCase
    {
        private const ORDER_ID     = 123;
        private const ORDER_NUMBER = 'CDEK-777';
        private const PACKAGES     = '[{"length":10,"width":20,"height":30}]';

        /** @var array<int, array> события в порядке появления: ['die', ...], ['json_success', ...], ['metabox', ...] */
        private array $events = [];

        private string $requestBody = self::PACKAGES;

        private array $originalServer;
        private array $originalGet;
        private array $originalRequest;

        protected function setUp(): void
        {
            parent::setUp();

            $this->events          = [];
            $this->requestBody     = self::PACKAGES;
            $this->originalServer  = $_SERVER;
            $this->originalGet     = $_GET;
            $this->originalRequest = $_REQUEST;

            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_GET['id']                = (string)self::ORDER_ID;
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

                throw new StopExecution();
            });
            Functions\when('wp_send_json_success')->alias(function ($data = null): void {
                $this->events[] = ['json_success', $data];

                throw new StopExecution();
            });
            Functions\when('wp_send_json_error')->alias(function ($data = null): void {
                $this->events[] = ['json_error', $data];

                throw new StopExecution();
            });

            Mockery::mock('alias:' . AdminOrderBox::class)
                   ->shouldReceive('createOrderMetaBox')
                   ->andReturnUsing(function ($post, array $meta = []): void {
                       $this->events[] = ['metabox', $post, $meta];
                   });
        }

        protected function tearDown(): void
        {
            $_SERVER  = $this->originalServer;
            $_GET     = $this->originalGet;
            $_REQUEST = $this->originalRequest;

            parent::tearDown();
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
            } catch (StopExecution $e) {
                // завершение запроса
            }

            return $this->events;
        }

        /** События без проверки nonce */
        private function eventsAfterNonce(array $events): array
        {
            self::assertSame(['nonce', 'official_cdek'], $events[0], 'nonce must be checked first');

            return array_values(array_slice($events, 1));
        }

        private function existingOrder(): void
        {
            $wcOrder = Mockery::mock('WC_Order');
            $wcOrder->shouldReceive('get_meta')->with('order_data')->andReturn(['number' => self::ORDER_NUMBER]);

            Functions\when('wc_get_order')->justReturn($wcOrder);
        }

        private function missingOrder(): void
        {
            Functions\when('wc_get_order')->justReturn(false);
        }

        /**
         * @param string   $actionClass
         * @param \Closure $behavior
         */
        private function stubAction(string $actionClass, Closure $behavior): void
        {
            $stub = new class($behavior) {
                private Closure $behavior;

                public function __construct(Closure $behavior)
                {
                    $this->behavior = $behavior;
                }

                public function __invoke(...$args)
                {
                    return ($this->behavior)(...$args);
                }
            };

            Mockery::mock('alias:' . $actionClass)->shouldReceive('new')->andReturn($stub);
        }

        // ---- barcode ----

        public function testBarcodeRejectsUsersWithoutEditPermission(): void
        {
            Functions\when('current_user_can')->alias(static fn(string $cap): bool => $cap !== 'edit_posts');

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'barcode']));

            self::assertSame([['die', -2, 403]], $events);
        }

        public function testBarcodeSendsFileDataOnSuccess(): void
        {
            $this->existingOrder();
            $this->stubAction(
                GenerateBarcodeAction::class,
                function (string $number): array {
                    self::assertSame(self::ORDER_NUMBER, $number);

                    return ['success' => true, 'data' => 'BASE64'];
                },
            );

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'barcode']));

            self::assertSame([['json_success', 'BASE64']], $events);
        }

        public function testBarcodeSendsErrorWhenGenerationFails(): void
        {
            $this->existingOrder();
            $failure = ['success' => false, 'message' => 'Failed to create barcode'];
            $this->stubAction(GenerateBarcodeAction::class, static fn(): array => $failure);

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'barcode']));

            self::assertSame([['json_error', $failure]], $events);
        }

        // ---- waybill ----

        public function testWaybillRejectsUsersWithoutEditPermission(): void
        {
            Functions\when('current_user_can')->justReturn(false);

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'waybill']));

            self::assertSame([['die', -2, 403]], $events);
        }

        public function testWaybillSendsFileDataOnSuccess(): void
        {
            $this->existingOrder();
            $this->stubAction(
                GenerateWaybillAction::class,
                function (string $number): array {
                    self::assertSame(self::ORDER_NUMBER, $number);

                    return ['success' => true, 'data' => 'BASE64'];
                },
            );

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'waybill']));

            self::assertSame([['json_success', 'BASE64']], $events);
        }

        public function testWaybillSendsErrorWhenGenerationFails(): void
        {
            $this->existingOrder();
            $failure = ['success' => false, 'message' => 'Failed to create waybill'];
            $this->stubAction(GenerateWaybillAction::class, static fn(): array => $failure);

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'waybill']));

            self::assertSame([['json_error', $failure]], $events);
        }

        public function testWaybillSendsExceptionAsErrorWhenOrderIsMissing(): void
        {
            $this->missingOrder();

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'waybill']));

            self::assertCount(1, $events);
            self::assertSame('json_error', $events[0][0]);
            self::assertInstanceOf(\Cdek\Exceptions\OrderNotFoundException::class, $events[0][1]);
        }

        // ---- create ----

        public function testCreateRejectsNonPostRequests(): void
        {
            $_SERVER['REQUEST_METHOD'] = 'GET';

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'create']));

            self::assertSame([['die', -2, 403]], $events);
        }

        public function testCreateRejectsUsersWithoutEditPermission(): void
        {
            Functions\when('current_user_can')->justReturn(false);

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'create']));

            self::assertSame([['die', -2, 403]], $events);
        }

        public function testCreateShowsErrorWhenOrderIsNotFound(): void
        {
            $this->missingOrder();

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'create']));

            self::assertCount(2, $events);
            self::assertSame('metabox', $events[0][0]);
            self::assertSame(self::ORDER_ID, $events[0][1]);
            self::assertCount(1, $events[0][2]['errors']);
            self::assertSame(['die', '', 0], $events[1]);
        }

        public function testCreateShowsErrorWhenBodyIsNotJson(): void
        {
            $this->existingOrder();
            $this->requestBody = '{not json';

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'create']));

            self::assertCount(2, $events);
            self::assertSame('metabox', $events[0][0]);
            self::assertInstanceOf(Order::class, $events[0][1]);
            self::assertSame(['errors' => ['Syntax error']], $events[0][2]);
            self::assertSame(['die', '', 0], $events[1]);
        }

        public function testCreateShowsSchemaValidationErrors(): void
        {
            $this->existingOrder();

            $error = Mockery::mock('WP_Error');
            $error->shouldReceive('get_error_messages')->andReturn(['packages[0] is not valid']);

            $received = null;
            Functions\when('rest_validate_array_value_from_schema')->alias(
                function ($value, array $schema, string $param) use ($error, &$received) {
                    $received = [$value, $schema['maxItems'], $param];

                    return $error;
                },
            );
            Functions\when('is_wp_error')->alias(static fn($value): bool => $value === $error);
            $this->stubAction(OrderCreateAction::class, static function (): void {
                throw new RuntimeException('must not be called for invalid packages');
            });

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'create']));

            self::assertSame([[['length' => 10, 'width' => 20, 'height' => 30]], 255, 'packages'], $received);
            self::assertCount(2, $events);
            self::assertInstanceOf(Order::class, $events[0][1]);
            self::assertSame(['errors' => ['packages[0] is not valid']], $events[0][2]);
            self::assertSame(['die', '', 0], $events[1]);
        }

        private function validPackages(): void
        {
            Functions\when('rest_validate_array_value_from_schema')->justReturn(true);
            Functions\when('is_wp_error')->justReturn(false);
        }

        public function testCreateCreatesOrderWithPackagesAndShowsNoErrorsOnSuccess(): void
        {
            $this->existingOrder();
            $this->validPackages();

            $received = null;
            $this->stubAction(
                OrderCreateAction::class,
                function (int $id, int $attempt, array $packages) use (&$received): ValidationResult {
                    $received = [$id, $attempt, $packages];

                    return new ValidationResult(true);
                },
            );

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'create']));

            self::assertSame([self::ORDER_ID, 0, [['length' => 10, 'width' => 20, 'height' => 30]]], $received);
            self::assertSame(
                [['metabox', self::ORDER_ID, ['errors' => null]], ['die', '', 0]],
                $events,
            );
        }

        public function testCreateShowsActionMessageWhenOrderWasNotCreated(): void
        {
            $this->existingOrder();
            $this->validPackages();
            $this->stubAction(
                OrderCreateAction::class,
                static fn(): ValidationResult => new ValidationResult(false, 'Order already exists'),
            );

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'create']));

            self::assertSame(
                [['metabox', self::ORDER_ID, ['errors' => ['Order already exists']]], ['die', '', 0]],
                $events,
            );
        }

        public function testCreateListsEveryServerValidationError(): void
        {
            $this->existingOrder();
            $this->validPackages();
            $this->stubAction(
                OrderCreateAction::class,
                static function (): void {
                    throw new InvalidRequestException([
                        ['message' => 'Phone is invalid'],
                        ['message' => 'Address is empty'],
                    ]);
                },
            );

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'create']));

            self::assertSame(
                [
                    [
                        'metabox',
                        self::ORDER_ID,
                        [
                            'errors' => [
                                'Server returned validation error: Phone is invalid',
                                'Server returned validation error: Address is empty',
                            ],
                        ],
                    ],
                    ['die', '', 0],
                ],
                $events,
            );
        }

        public function testCreateShowsServerErrorMessageForOtherPluginExceptions(): void
        {
            $this->existingOrder();
            $this->validPackages();
            $this->stubAction(
                OrderCreateAction::class,
                static function (): void {
                    throw new ShippingNotFoundException();
                },
            );

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'create']));

            self::assertCount(2, $events);
            self::assertSame('metabox', $events[0][0]);
            self::assertCount(1, $events[0][2]['errors']);
            self::assertStringStartsWith('Server returned an error: ', $events[0][2]['errors'][0]);
            self::assertSame(['die', '', 0], $events[1]);
        }

        // ---- delete ----

        public function testDeleteRejectsNonPostRequests(): void
        {
            $_SERVER['REQUEST_METHOD'] = 'GET';

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'delete']));

            self::assertSame([['die', -2, 403]], $events);
        }

        public function testDeleteRejectsUsersWithoutEditPermission(): void
        {
            Functions\when('current_user_can')->justReturn(false);

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'delete']));

            self::assertSame([['die', -2, 403]], $events);
        }

        public function testDeleteShowsSuccessMessage(): void
        {
            $this->stubAction(
                OrderDeleteAction::class,
                function (int $id): ValidationResult {
                    self::assertSame(self::ORDER_ID, $id);

                    return new ValidationResult(true, 'Waybill has been deleted');
                },
            );

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'delete']));

            self::assertSame(
                [['metabox', self::ORDER_ID, ['success' => ['Waybill has been deleted']]], ['die', '', 0]],
                $events,
            );
        }

        public function testDeleteShowsErrorMessage(): void
        {
            $this->stubAction(
                OrderDeleteAction::class,
                static fn(): ValidationResult => new ValidationResult(false, 'Order was not deleted'),
            );

            $events = $this->eventsAfterNonce($this->execute([OrderController::class, 'delete']));

            self::assertSame(
                [['metabox', self::ORDER_ID, ['errors' => ['Order was not deleted']]], ['die', '', 0]],
                $events,
            );
        }

        // ---- регистрация хуков ----

        public function testInvokeRegistersNothingOutsideAjaxRequests(): void
        {
            Functions\when('wp_doing_ajax')->justReturn(false);
            Functions\expect('add_action')->never();

            (new OrderController())();

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

            (new OrderController())();

            self::assertSame(
                [
                    'wp_ajax_official_cdek-create'  => [OrderController::class, 'create'],
                    'wp_ajax_official_cdek-delete'  => [OrderController::class, 'delete'],
                    'wp_ajax_official_cdek-waybill' => [OrderController::class, 'waybill'],
                    'wp_ajax_official_cdek-barcode' => [OrderController::class, 'barcode'],
                ],
                $registered,
            );
        }
    }
}

namespace Cdek\Tests\Unit\Controllers {

    /**
     * Моделирует то, что `wp_die()`/`wp_send_json_*()` не возвращают управление.
     */
    final class StopExecution extends \RuntimeException
    {
    }
}
