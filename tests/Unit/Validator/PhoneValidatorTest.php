<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Validator;

use Brain\Monkey\Functions;
use Cdek\CoreApi;
use Cdek\Exceptions\External\ApiException;
use Cdek\Exceptions\External\HttpClientException;
use Cdek\Exceptions\InvalidPhoneException;
use Cdek\Loader;
use Cdek\Tests\TestCase;
use Cdek\Validator\PhoneValidator;
use Exception;
use Mockery;
use ReflectionClass;
use Throwable;

final class PhoneValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $reflection = (new ReflectionClass(Loader::class))->getProperty('pluginName');
        $reflection->setAccessible(true);
        $reflection->setValue(null, 'CDEK Delivery');

        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();
    }

    /**
     * `HttpClientException`, как и все настоящие исключения, никогда не
     * получает `code` через свой конструктор (см. `HttpClient::processRequest()`
     * - код туда просто не прокидывается). `PhoneValidator` проверяет
     * `$e->getCode() === 422`, так что для теста код выставляется напрямую
     * рефлексией в унаследованное от `Exception` защищённое свойство.
     */
    private function createHttpClientException(int $code): HttpClientException
    {
        return $this->withCode(new HttpClientException([]), $code);
    }

    /**
     * @template T of Exception
     * @param  T  $exception
     * @return T
     */
    private function withCode(Exception $exception, int $code): Exception
    {
        $property = (new ReflectionClass(Exception::class))->getProperty('code');
        $property->setAccessible(true);
        $property->setValue($exception, $code);

        return $exception;
    }

    private function catchException(callable $callback): Throwable
    {
        try {
            $callback();
        } catch (Throwable $e) {
            return $e;
        }

        self::fail('Expected exception was not thrown.');
    }

    public function testInvokeReturnsPhoneValidatedByCoreApi(): void
    {
        Mockery::mock('overload:' . CoreApi::class)
            ->shouldReceive('validatePhone')
            ->once()
            ->with('+79991234567', 'RU')
            ->andReturn('+79991234567');

        $result = (new PhoneValidator())('+79991234567', 'RU');

        self::assertSame('+79991234567', $result);
    }

    public function testInvokePassesNullCountryCodeWhenNotGiven(): void
    {
        Mockery::mock('overload:' . CoreApi::class)
            ->shouldReceive('validatePhone')
            ->once()
            ->with('123', null)
            ->andReturn('123');

        $result = (new PhoneValidator())('123');

        self::assertSame('123', $result);
    }

    public function testInvokeConvertsHttpClientExceptionToInvalidPhoneExceptionWhenCodeIs422(): void
    {
        Mockery::mock('overload:' . CoreApi::class)
            ->shouldReceive('validatePhone')
            ->once()
            ->andThrow($this->createHttpClientException(422));

        $exception = $this->catchException(static fn() => (new PhoneValidator())('bad-phone'));

        self::assertInstanceOf(InvalidPhoneException::class, $exception);
        self::assertSame(['phone' => 'bad-phone'], $exception->getData());
    }

    /**
     * @dataProvider nonValidationCodeProvider
     */
    public function testInvokeRethrowsHttpClientExceptionWhenCodeIsNot422(int $code): void
    {
        $original = $this->createHttpClientException($code);

        Mockery::mock('overload:' . CoreApi::class)
            ->shouldReceive('validatePhone')
            ->once()
            ->andThrow($original);

        $exception = $this->catchException(static fn() => (new PhoneValidator())('+79991234567'));

        self::assertSame($original, $exception);
    }

    public static function nonValidationCodeProvider(): array
    {
        return [
            'default zero code (real-world construction path)' => [0],
            'unrelated client error code'                      => [400],
        ];
    }

    public function testInvokeDoesNotCatchExceptionsOtherThanHttpClientException(): void
    {
        $original = new ApiException([]);

        Mockery::mock('overload:' . CoreApi::class)
            ->shouldReceive('validatePhone')
            ->once()
            ->andThrow($original);

        $exception = $this->catchException(static fn() => (new PhoneValidator())('+79991234567'));

        self::assertSame($original, $exception);
    }

    /**
     * Проверяет именно узость `catch (HttpClientException $e)`, не `catch (ApiException $e)`
     * (родитель `HttpClientException`) - код 422 на родительском типе не должен
     * конвертироваться в `InvalidPhoneException`, catch-блок его вообще не должен перехватывать.
     */
    public function testInvokeDoesNotConvertParentApiExceptionEvenWithCode422(): void
    {
        $original = $this->withCode(new ApiException([]), 422);

        Mockery::mock('overload:' . CoreApi::class)
            ->shouldReceive('validatePhone')
            ->once()
            ->andThrow($original);

        $exception = $this->catchException(static fn() => (new PhoneValidator())('+79991234567'));

        self::assertSame($original, $exception);
    }
}
