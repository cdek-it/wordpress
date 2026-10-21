<?php

declare(strict_types=1);

namespace {
    // `WP_REST_Server::READABLE` - реальный класс ядра WordPress, недоступен в
    // тестовом окружении (composer тянет только stub-пакет для PHPStan, не для
    // рантайма) - минимальная заглушка с нужной константой, больше нигде в
    // сьюте не объявляется.
    if (!class_exists('WP_REST_Server')) {
        class WP_REST_Server
        {
            public const READABLE = 'GET';
        }
    }
}

namespace Cdek\Tests\Unit\Transport {

    use Brain\Monkey\Functions;
    use Cdek\Exceptions\External\ApiException;
    use Cdek\Exceptions\External\EntityNotFoundException;
    use Cdek\Exceptions\External\HttpClientException;
    use Cdek\Exceptions\External\HttpServerException;
    use Cdek\Exceptions\External\InvalidRequestException;
    use Cdek\Loader;
    use Cdek\Tests\TestCase;
    use Cdek\Transport\HttpClient;
    use Cdek\Transport\HttpResponse;
    use Mockery;
    use ReflectionClass;

    /**
     * Реальный `wp_remote_retrieve_headers()` возвращает объект с
     * `getAll(): array`, а не голый массив - `method_exists()` в HttpClient падает
     * с TypeError на массиве, а на `Mockery::mock()` без интерфейса `getAll()`
     * заводится через `__call()` и не виден `method_exists()` (рефлексия его не
     * находит). Нужен настоящий класс с реальным методом.
     */
    final class HeadersDictionaryDouble
    {
        private array $headers;

        public function __construct(array $headers)
        {
            $this->headers = $headers;
        }

        public function getAll(): array
        {
            return $this->headers;
        }
    }

    final class HttpClientTest extends TestCase
    {
        protected string $testUrl = 'https://example.test';

        protected function setUp(): void
        {
            parent::setUp();

            // Exception-конструкторы (ApiException -> ... -> ExceptionContract) читают
            // Loader::getPluginName()/Loader::debug(), которые вне полной загрузки
            // плагина не инициализированы - выставляем typed static-свойства рефлексией,
            // как в HttpResponseTest.
            $this->setLoaderStatic('pluginName', 'CDEK Delivery');
            $this->setLoaderStatic('pluginVersion', '4.0.0');
            $this->setLoaderStatic('debug', false);

            Functions\when('esc_html')->returnArg();
            Functions\when('esc_html__')->returnArg();
            Functions\when('wp_json_encode')->alias(static fn($data) => json_encode($data));
            Functions\when('get_user_locale')->justReturn('ru_RU');
            Functions\when('wp_generate_uuid4')->justReturn('11111111-1111-1111-1111-111111111111');
            Functions\when('get_bloginfo')->justReturn('6.4');
            Functions\when('is_wp_error')->justReturn(false);
            Functions\when('wc_get_logger')->justReturn(null);
        }

        /**
         * @param  mixed  $value
         */
        private function setLoaderStatic(string $property, $value): void
        {
            $reflection = (new ReflectionClass(Loader::class))->getProperty($property);
            $reflection->setAccessible(true);
            $reflection->setValue(null, $value);
        }

        private function mockWpRemoteResponse(
            int $statusCode,
            string $body,
            array $headers = ['content-type' => 'application/json']
        ): void {
            Functions\when('wp_remote_request')->justReturn(['__marker' => true]);
            Functions\when('wp_remote_retrieve_response_code')->justReturn($statusCode);
            Functions\when('wp_remote_retrieve_body')->justReturn($body);
            Functions\when('wp_remote_retrieve_headers')->justReturn(new HeadersDictionaryDouble($headers));
        }

        public function testProcessRequestBuildsHttpResponseFromWpRemoteResponse(): void
        {
            $this->mockWpRemoteResponse(201, '{"entity":{"uuid":"abc"}}');

            $response = HttpClient::processRequest($this->testUrl . '/orders', 'POST');

            self::assertInstanceOf(HttpResponse::class, $response);
            self::assertSame(201, $response->getStatusCode());
            self::assertSame(['uuid' => 'abc'], $response->entity());
        }

        public function testProcessRequestUsesGetAllWhenHeadersImplementCaseInsensitiveDictionary(): void
        {
            Functions\when('wp_remote_request')->justReturn(['__marker' => true]);
            Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
            Functions\when('wp_remote_retrieve_body')->justReturn('{}');
            Functions\when('wp_remote_retrieve_headers')->justReturn(
                new HeadersDictionaryDouble(['content-type' => 'application/json']),
            );

            $response = HttpClient::processRequest($this->testUrl, 'GET');

            self::assertSame(['content-type' => 'application/json'], $response->getHeaders());
        }

        public function testProcessRequestThrowsApiExceptionOnWpError(): void
        {
            $error = Mockery::mock('WP_Error');
            $error->shouldReceive('get_error_code')->andReturn('http_request_failed');
            $error->shouldReceive('get_error_message')->andReturn('Connection timed out');

            Functions\when('wp_remote_request')->justReturn($error);
            Functions\when('is_wp_error')->justReturn(true);
            // Путь построения ApiException дополнительно бьёт в tryGetRequesterIp(),
            // который сам делает отдельный wp_remote_get() - тоже глушим.
            Functions\when('wp_remote_get')->justReturn([]);
            // Пустая строка -> tryGetRequesterIp() возвращает null до headers_sent()/
            // header() - последние два непереопределяемы через Patchwork без правки
            // patchwork.json, поэтому просто не должны быть достигнуты в этом тесте.
            Functions\when('wp_remote_retrieve_body')->justReturn('');

            $this->expectException(ApiException::class);

            HttpClient::processRequest($this->testUrl, 'GET');
        }

        public function testSendJsonRequestReturnsResponseOnSuccess(): void
        {
            $this->mockWpRemoteResponse(200, '{"entity":{"ok":true}}');

            $response = HttpClient::sendJsonRequest($this->testUrl, 'POST', 'token', ['foo' => 'bar']);

            self::assertSame(['ok' => true], $response->entity());
        }

        public function testSendJsonRequestJsonEncodesBodyForNonReadableMethod(): void
        {
            Functions\expect('wp_remote_request')
                ->once()
                ->with(
                    Mockery::any(),
                    Mockery::on(static fn(array $args) => ($args['body'] ?? null) === '{"foo":"bar"}'),
                )
                ->andReturn(['__marker' => true]);
            Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
            Functions\when('wp_remote_retrieve_body')->justReturn('{}');
            Functions\when('wp_remote_retrieve_headers')->justReturn(
                new HeadersDictionaryDouble(['content-type' => 'application/json']),
            );

            HttpClient::sendJsonRequest($this->testUrl, 'POST', 'token', ['foo' => 'bar']);

            self::assertTrue(true);
        }

        public function testSendJsonRequestPassesRawDataForReadableMethod(): void
        {
            Functions\expect('wp_remote_request')
                ->once()
                ->with(
                    Mockery::any(),
                    Mockery::on(static fn(array $args) => ($args['body'] ?? null) === ['foo' => 'bar']),
                )
                ->andReturn(['__marker' => true]);
            Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
            Functions\when('wp_remote_retrieve_body')->justReturn('{}');
            Functions\when('wp_remote_retrieve_headers')->justReturn(
                new HeadersDictionaryDouble(['content-type' => 'application/json']),
            );

            HttpClient::sendJsonRequest($this->testUrl, \WP_REST_Server::READABLE, 'token', ['foo' => 'bar']);

            self::assertTrue(true);
        }

        public function testSendJsonRequestThrowsHttpServerExceptionOnServerError(): void
        {
            $this->mockWpRemoteResponse(500, '{"error":{"code":"internal"}}');

            $this->expectException(HttpServerException::class);

            HttpClient::sendJsonRequest($this->testUrl, 'GET', 'token');
        }

        public function testSendJsonRequestThrowsInvalidRequestExceptionOnUnprocessableEntity(): void
        {
            $this->mockWpRemoteResponse(422, '{"error":{"fields":["phone"]}}');

            $this->expectException(InvalidRequestException::class);

            HttpClient::sendJsonRequest($this->testUrl, 'POST', 'token');
        }

        public function testSendJsonRequestThrowsEntityNotFoundExceptionOnNotFound(): void
        {
            $this->mockWpRemoteResponse(404, '{"error":{"message":"not found","code":"missing"}}');

            $this->expectException(EntityNotFoundException::class);

            HttpClient::sendJsonRequest($this->testUrl, 'GET', 'token');
        }

        public function testSendJsonRequestThrowsInvalidRequestExceptionWhenLegacyRequestStateIsInvalid(): void
        {
            // Статус 200 (не client/server error, не 404/422) - но легаси-обёртка
            // requests[0].state=INVALID сигнализирует ошибку валидации иначе.
            $this->mockWpRemoteResponse(
                200,
                '{"requests":[{"state":"INVALID","errors":["bad phone"]}]}',
            );

            $this->expectException(InvalidRequestException::class);

            HttpClient::sendJsonRequest($this->testUrl, 'POST', 'token');
        }

        public function testSendJsonRequestThrowsHttpClientExceptionOnGenericClientError(): void
        {
            $this->mockWpRemoteResponse(400, '{"error":{"code":"bad_request"}}');

            $this->expectException(HttpClientException::class);

            HttpClient::sendJsonRequest($this->testUrl, 'POST', 'token');
        }
    }
}
