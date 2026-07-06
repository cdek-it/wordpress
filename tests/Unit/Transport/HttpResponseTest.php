<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Transport;

use Brain\Monkey\Functions;
use Cdek\Exceptions\External\UnparsableAnswerException;
use Cdek\Loader;
use Cdek\Tests\TestCase;
use Cdek\Transport\HttpResponse;
use ReflectionClass;

final class HttpResponseTest extends TestCase
{
    protected string $testUrl = 'https://example.test';

    protected function setUp(): void
    {
        parent::setUp();

        // Exception-конструкторы (UnparsableAnswerException -> ... -> ExceptionContract)
        // читают Loader::getPluginName(), которое читает typed static property без
        // дефолта - вне полноценной загрузки плагина оно не инициализировано.
        $pluginName = (new ReflectionClass(Loader::class))->getProperty('pluginName');
        $pluginName->setAccessible(true);
        $pluginName->setValue(null, 'CDEK Delivery');

        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();
    }

    public function testBodyReturnsRawBody(): void
    {
        $response = new HttpResponse(200, 'raw body', [], $this->testUrl, 'GET');

        self::assertSame('raw body', $response->body());
    }

    public function testGetHeadersReturnsHeaders(): void
    {
        $headers  = ['content-type' => 'application/json'];
        $response = new HttpResponse(200, '{}', $headers, $this->testUrl, 'GET');

        self::assertSame($headers, $response->getHeaders());
    }

    public function testGetStatusCodeReturnsStatusCode(): void
    {
        $response = new HttpResponse(201, '', [], $this->testUrl, 'POST');

        self::assertSame(201, $response->getStatusCode());
    }

    /**
     * @dataProvider statusCodeProvider
     */
    public function testStatusHelpers(int $statusCode, bool $success, bool $clientError, bool $serverError): void
    {
        $response = new HttpResponse($statusCode, '', [], $this->testUrl, 'GET');

        self::assertSame($success, $response->isSuccess());
        self::assertSame($clientError, $response->isClientError());
        self::assertSame($serverError, $response->isServerError());
    }

    public static function statusCodeProvider(): array
    {
        return [
            '200 is success'      => [200, true, false, false],
            '299 is success'      => [299, true, false, false],
            '300 is not success'  => [300, false, false, false],
            '400 is client error' => [400, false, true, false],
            '499 is client error' => [499, false, true, false],
            '500 is server error' => [500, false, false, true],
            '599 is server error' => [599, false, false, true],
        ];
    }

    public function testJsonDecodesBodyWhenContentTypeIsJson(): void
    {
        $response = new HttpResponse(
            200,
            '{"data":{"foo":"bar"}}',
            ['content-type' => 'application/json; charset=utf-8'],
            $this->testUrl,
            'GET',
        );

        self::assertSame(['data' => ['foo' => 'bar']], $response->json());
    }

    public function testJsonThrowsWhenContentTypeIsMissing(): void
    {
        $response = new HttpResponse(200, '{}', [], $this->testUrl, 'GET');

        $this->expectException(UnparsableAnswerException::class);

        $response->json();
    }

    public function testJsonThrowsWhenContentTypeIsNotJson(): void
    {
        $response = new HttpResponse(
            200,
            '<html></html>',
            ['content-type' => 'text/html'],
            $this->testUrl,
            'GET',
        );

        $this->expectException(UnparsableAnswerException::class);

        $response->json();
    }

    public function testJsonThrowsWhenBodyIsInvalidJson(): void
    {
        $response = new HttpResponse(
            200,
            '{invalid json}',
            ['content-type' => 'application/json'],
            $this->testUrl,
            'GET',
        );

        $this->expectException(UnparsableAnswerException::class);

        $response->json();
    }

    public function testDataReturnsDataKey(): void
    {
        $response = new HttpResponse(
            200,
            '{"data":["a","b"]}',
            ['content-type' => 'application/json'],
            $this->testUrl,
            'GET',
        );

        self::assertSame(['a', 'b'], $response->data());
    }

    public function testDataReturnsEmptyArrayWhenMissing(): void
    {
        $response = new HttpResponse(
            200,
            '{}',
            ['content-type' => 'application/json'],
            $this->testUrl,
            'GET',
        );

        self::assertSame([], $response->data());
    }

    public function testEntityReturnsEntityKey(): void
    {
        $response = new HttpResponse(
            200,
            '{"entity":{"uuid":"123"}}',
            ['content-type' => 'application/json'],
            $this->testUrl,
            'GET',
        );

        self::assertSame(['uuid' => '123'], $response->entity());
    }

    public function testEntityReturnsNullWhenMissing(): void
    {
        $response = new HttpResponse(
            200,
            '{}',
            ['content-type' => 'application/json'],
            $this->testUrl,
            'GET',
        );

        self::assertNull($response->entity());
    }

    public function testErrorReturnsErrorKeyWhenPresent(): void
    {
        $response = new HttpResponse(
            400,
            '{"error":{"code":"bad_request"}}',
            ['content-type' => 'application/json'],
            $this->testUrl,
            'GET',
        );

        self::assertSame(['code' => 'bad_request'], $response->error());
    }

    public function testErrorFallsBackToLegacyRequestErrors(): void
    {
        $response = new HttpResponse(
            400,
            '{"requests":[{"errors":[{"code":"legacy_error"}]}]}',
            ['content-type' => 'application/json'],
            $this->testUrl,
            'GET',
        );

        self::assertSame(['code' => 'legacy_error'], $response->error());
    }

    public function testErrorReturnsNullWhenNothingPresent(): void
    {
        $response = new HttpResponse(
            200,
            '{}',
            ['content-type' => 'application/json'],
            $this->testUrl,
            'GET',
        );

        self::assertNull($response->error());
    }

    public function testLegacyRequestErrorsReturnsErrorsArray(): void
    {
        $response = new HttpResponse(
            400,
            '{"requests":[{"errors":["oops"]}]}',
            ['content-type' => 'application/json'],
            $this->testUrl,
            'GET',
        );

        self::assertSame(['oops'], $response->legacyRequestErrors());
    }

    public function testLegacyRequestErrorsReturnsEmptyArrayWhenMissing(): void
    {
        $response = new HttpResponse(
            200,
            '{}',
            ['content-type' => 'application/json'],
            $this->testUrl,
            'GET',
        );

        self::assertSame([], $response->legacyRequestErrors());
    }

    /**
     * @dataProvider missInvalidLegacyRequestProvider
     */
    public function testMissInvalidLegacyRequest(string $body, bool $expected): void
    {
        $response = new HttpResponse(
            200,
            $body,
            ['content-type' => 'application/json'],
            $this->testUrl,
            'GET',
        );

        self::assertSame($expected, $response->missInvalidLegacyRequest());
    }

    public static function missInvalidLegacyRequestProvider(): array
    {
        return [
            'no requests at all'      => ['{}', true],
            'empty state'             => ['{"requests":[{"state":""}]}', true],
            'state is INVALID'        => ['{"requests":[{"state":"INVALID"}]}', false],
            'state is something else' => ['{"requests":[{"state":"SUCCESSFUL"}]}', true],
        ];
    }

    public function testMissInvalidLegacyRequestReturnsTrueWhenBodyIsUnparsable(): void
    {
        $response = new HttpResponse(200, 'not json', [], $this->testUrl, 'GET');

        self::assertTrue($response->missInvalidLegacyRequest());
    }

    public function testNextCursorReturnsCursorNext(): void
    {
        $response = new HttpResponse(
            200,
            '{"cursor":{"next":"abc123"}}',
            ['content-type' => 'application/json'],
            $this->testUrl,
            'GET',
        );

        self::assertSame('abc123', $response->nextCursor());
    }

    public function testNextCursorReturnsNullWhenMissing(): void
    {
        $response = new HttpResponse(
            200,
            '{}',
            ['content-type' => 'application/json'],
            $this->testUrl,
            'GET',
        );

        self::assertNull($response->nextCursor());
    }

    public function testRelatedReturnsRelatedEntities(): void
    {
        $response = new HttpResponse(
            200,
            '{"related_entities":[{"type":"order"}]}',
            ['content-type' => 'application/json'],
            $this->testUrl,
            'GET',
        );

        self::assertSame([['type' => 'order']], $response->related());
    }

    public function testRelatedReturnsEmptyArrayWhenMissing(): void
    {
        $response = new HttpResponse(
            200,
            '{}',
            ['content-type' => 'application/json'],
            $this->testUrl,
            'GET',
        );

        self::assertSame([], $response->related());
    }
}
