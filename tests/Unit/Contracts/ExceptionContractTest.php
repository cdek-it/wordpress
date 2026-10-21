<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Contracts;

use Brain\Monkey\Functions;
use Cdek\Contracts\ExceptionContract;
use Cdek\Loader;
use Cdek\Tests\TestCase;
use ReflectionClass;

/**
 * Голый дублёр `ExceptionContract` без переопределения конструктора - как
 * если бы наследник вообще не задавал `$this->message` до `parent::__construct()`.
 */
final class ExceptionContractDouble extends ExceptionContract
{
}

/**
 * Дублёр, повторяющий реальный паттерн наследников (см. `ApiException`,
 * `ShopRegistrationException`) - выставляет `$this->message`/`$key`/`$status`
 * до вызова `parent::__construct()`.
 */
final class ExceptionContractWithOverridesDouble extends ExceptionContract
{
    protected string $key = 'custom_key';
    protected int $status = 422;

    public function __construct(?array $data = null)
    {
        $this->message = 'Custom message';

        parent::__construct($data);
    }
}

final class ExceptionContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $reflection = (new ReflectionClass(Loader::class))->getProperty('pluginName');
        $reflection->setAccessible(true);
        $reflection->setValue(null, 'CDEK Delivery');

        Functions\when('esc_html__')->returnArg();
    }

    public function testConstructorFallsBackToUnknownErrorWhenNoMessageIsSet(): void
    {
        $exception = new ExceptionContractDouble();

        self::assertSame('[CDEK Delivery] Unknown error', $exception->getMessage());
    }

    public function testConstructorKeepsOwnMessageSetBeforeParentConstructor(): void
    {
        $exception = new ExceptionContractWithOverridesDouble();

        self::assertSame('[CDEK Delivery] Custom message', $exception->getMessage());
    }

    public function testGetDataReturnsEmptyArrayWhenNoDataGiven(): void
    {
        $exception = new ExceptionContractDouble();

        self::assertSame([], $exception->getData());
    }

    public function testGetDataReturnsGivenData(): void
    {
        $exception = new ExceptionContractDouble(['foo' => 'bar']);

        self::assertSame(['foo' => 'bar'], $exception->getData());
    }

    public function testGetKeyReturnsDefaultKeyWhenNotOverridden(): void
    {
        $exception = new ExceptionContractDouble();

        self::assertSame('cdek_error', $exception->getKey());
    }

    public function testGetKeyReturnsOverriddenKey(): void
    {
        $exception = new ExceptionContractWithOverridesDouble();

        self::assertSame('custom_key', $exception->getKey());
    }

    public function testGetStatusCodeReturnsDefaultStatusWhenNotOverridden(): void
    {
        $exception = new ExceptionContractDouble();

        self::assertSame(500, $exception->getStatusCode());
    }

    public function testGetStatusCodeReturnsOverriddenStatus(): void
    {
        $exception = new ExceptionContractWithOverridesDouble();

        self::assertSame(422, $exception->getStatusCode());
    }
}
