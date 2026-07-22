<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Exceptions;

use Brain\Monkey\Functions;
use Cdek\Contracts\ExceptionContract;
use Cdek\Exceptions\CacheException;
use Cdek\Exceptions\External\ApiException;
use Cdek\Exceptions\External\CoreAuthException;
use Cdek\Exceptions\External\EntityNotFoundException;
use Cdek\Exceptions\External\HttpClientException;
use Cdek\Exceptions\External\HttpServerException;
use Cdek\Exceptions\External\InvalidRequestException;
use Cdek\Exceptions\External\LegacyAuthException;
use Cdek\Exceptions\External\UnparsableAnswerException;
use Cdek\Exceptions\InvalidPhoneException;
use Cdek\Exceptions\OrderNotFoundException;
use Cdek\Exceptions\ScheduledTaskException;
use Cdek\Exceptions\ShippingNotFoundException;
use Cdek\Exceptions\ShopRegistrationException;
use Cdek\Loader;
use Cdek\Tests\TestCase;
use ReflectionClass;

/**
 * Один тест на все тонкие наследники `ExceptionContract` из `src/Exceptions`:
 * dataProvider фиксирует их фактическое сопоставление key/message/data/status
 * как оно есть в коде сейчас (в т.ч. неочевидные места, см. комментарии
 * в самом provider'е), а не то, каким оно "должно" быть.
 */
final class ExceptionsMappingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $reflection = (new ReflectionClass(Loader::class))->getProperty('pluginName');
        $reflection->setAccessible(true);
        $reflection->setValue(null, 'CDEK Delivery');

        Functions\when('esc_html__')->returnArg();
    }

    /**
     * @dataProvider exceptionProvider
     */
    public function testKeyMessageDataAndStatusMapping(
        callable $factory,
        string $expectedKey,
        string $expectedMessage,
        array $expectedData,
        int $expectedStatus
    ): void {
        $exception = $factory();

        self::assertInstanceOf(ExceptionContract::class, $exception);
        self::assertSame($expectedKey, $exception->getKey());
        self::assertSame('[CDEK Delivery] '.$expectedMessage, $exception->getMessage());
        self::assertSame($expectedData, $exception->getData());
        self::assertSame($expectedStatus, $exception->getStatusCode());
    }

    public static function exceptionProvider(): array
    {
        return [
            'CacheException' => [
                static fn (): CacheException => new CacheException('/var/www/cache'),
                'cache.fs.rights',
                'Cache directory is not writable: /var/www/cache',
                ['path' => '/var/www/cache'],
                500,
            ],
            'InvalidPhoneException, непустой телефон' => [
                static fn (): InvalidPhoneException => new InvalidPhoneException('+79991234567'),
                'validation.phone',
                'Incorrect recipient phone number: +79991234567',
                ['phone' => '+79991234567'],
                500,
            ],
            /**
             * Отдельная ветка конструктора для пустой строки телефона -
             * своё сообщение и своё значение data.
             */
            'InvalidPhoneException, пустой телефон' => [
                static fn (): InvalidPhoneException => new InvalidPhoneException(''),
                'validation.phone',
                'Recipient phone number is empty',
                ['phone' => ''],
                500,
            ],
            'OrderNotFoundException' => [
                static fn (): OrderNotFoundException => new OrderNotFoundException(),
                'order.missing',
                'Order not found',
                [],
                500,
            ],
            /**
             * `ScheduledTaskException` не переопределяет конструктор и не
             * задаёт `$this->message`, поэтому фактически всегда получает
             * дефолтное "Unknown error" из `ExceptionContract` - это не баг
             * теста, а текущее поведение src/.
             */
            'ScheduledTaskException' => [
                static fn (): ScheduledTaskException => new ScheduledTaskException(['task' => 'sync-orders']),
                'scheduled.task',
                'Unknown error',
                ['task' => 'sync-orders'],
                500,
            ],
            'ShippingNotFoundException' => [
                static fn (): ShippingNotFoundException => new ShippingNotFoundException(),
                'shipping.missing',
                'Shipping not found',
                [],
                500,
            ],
            'ShopRegistrationException' => [
                static fn (): ShopRegistrationException => new ShopRegistrationException(),
                'shop.exchange',
                'Shop registration error',
                [],
                500,
            ],
            'ApiException, дефолтное сообщение' => [
                static fn (): ApiException => new ApiException(['foo' => 'bar']),
                'api.general',
                'API error',
                ['foo' => 'bar'],
                500,
            ],
            'ApiException, переданное сообщение' => [
                static fn (): ApiException => new ApiException(['foo' => 'bar'], 'Custom API error'),
                'api.general',
                'Custom API error',
                ['foo' => 'bar'],
                500,
            ],
            'CoreAuthException' => [
                static fn (): CoreAuthException => new CoreAuthException(),
                'auth.core',
                'Core auth error',
                [],
                401,
            ],
            /**
             * В отличие от остальных наследников, `EntityNotFoundException`
             * берёт сообщение из `$error['message']` напрямую, без
             * `esc_html__()` - фиксируем как есть.
             */
            'EntityNotFoundException' => [
                static fn (): EntityNotFoundException => new EntityNotFoundException([
                    'message' => 'Entity not found',
                    'code'    => 'ENTITY_404',
                ]),
                'http.missing',
                'Entity not found',
                ['error' => 'ENTITY_404'],
                500,
            ],
            'HttpClientException' => [
                static fn (): HttpClientException => new HttpClientException(['field' => 'value']),
                'http.client',
                'Client request error',
                ['field' => 'value'],
                500,
            ],
            'HttpServerException' => [
                static fn (): HttpServerException => new HttpServerException(['field' => 'value']),
                'http.server',
                'Server request error',
                ['field' => 'value'],
                500,
            ],
            'InvalidRequestException' => [
                static fn (): InvalidRequestException => new InvalidRequestException(
                    ['phone' => 'invalid'],
                    ['phone' => '123'],
                ),
                'http.validation',
                'Invalid API request',
                ['errors' => ['phone' => 'invalid'], 'request' => ['phone' => '123']],
                500,
            ],
            'LegacyAuthException' => [
                static fn (): LegacyAuthException => new LegacyAuthException(['reason' => 'expired']),
                'auth.int',
                'Failed to get the token',
                ['reason' => 'expired'],
                401,
            ],
            'UnparsableAnswerException' => [
                static fn (): UnparsableAnswerException => new UnparsableAnswerException(
                    '<html>',
                    'https://example.test/orders',
                    'POST',
                ),
                'api.parse',
                'Unable to parse API answer',
                ['answer' => '<html>', 'url' => 'https://example.test/orders', 'method' => 'POST'],
                500,
            ],
        ];
    }
}
