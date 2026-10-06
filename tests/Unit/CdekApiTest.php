<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit;

use Brain\Monkey\Functions;
use Cdek\CdekApi;
use Cdek\Config;
use Cdek\Exceptions\External\ApiException;
use Cdek\Exceptions\External\LegacyAuthException;
use Cdek\Helpers\LegacyTokenStorage;
use Cdek\Loader;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Cdek\Transport\HttpClient;
use Cdek\Transport\HttpResponse;
use Mockery;
use Mockery\MockInterface;
use ReflectionProperty;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class CdekApiTest extends TestCase
{
    private const TOKEN = 'Bearer test-token';
    private const LIVE  = 'https://api.cdek.ru/v2/';

    /** @var array<int, array{url: string, method: string, token: ?string, data: ?array}> */
    private array $requests = [];

    /** @var HttpResponse[] очередь ответов HttpClient::sendJsonRequest */
    private array $responses = [];

    /** @var mixed */
    private $originalEnv;

    private MockInterface $http;

    /** Если задано, HttpClient::sendJsonRequest() бросает это исключение вместо ответа */
    private ?\Throwable $transportError = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requests       = [];
        $this->responses      = [];
        $this->transportError = null;
        $this->originalEnv = $_ENV['CDEK_REST_API'] ?? null;
        unset($_ENV['CDEK_REST_API']);

        Functions\when('esc_html')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('wp_parse_url')->alias(static fn($url, $component = -1) => parse_url($url, $component));
        Functions\when('wc_get_logger')->justReturn(null);

        $property = new ReflectionProperty(Loader::class, 'pluginName');
        $property->setAccessible(true);
        $property->setValue(null, 'CDEK');

        Mockery::mock('overload:' . LegacyTokenStorage::class)->shouldReceive('getToken')->andReturn(self::TOKEN);

        $this->http = Mockery::mock('alias:' . HttpClient::class);
        $this->http->shouldReceive('sendJsonRequest')->andReturnUsing(
            function (string $url, string $method, ?string $token, ?array $data = null) {
                $this->requests[] = ['url' => $url, 'method' => $method, 'token' => $token, 'data' => $data];

                if ($this->transportError !== null) {
                    throw $this->transportError;
                }

                return array_shift($this->responses);
            },
        );
    }

    protected function tearDown(): void
    {
        if ($this->originalEnv === null) {
            unset($_ENV['CDEK_REST_API']);
        } else {
            $_ENV['CDEK_REST_API'] = $this->originalEnv;
        }

        parent::tearDown();
    }

    private function settings(array $values = []): MockInterface
    {
        $method = Mockery::mock('alias:' . ShippingMethod::class);

        foreach (array_merge(
            ['test_mode' => false, 'client_id' => 'my-id', 'client_secret' => 'my-secret'],
            $values,
        ) as $key => $value) {
            $method->$key = $value;
        }

        return $method;
    }

    private function api(array $settings = []): CdekApi
    {
        return new CdekApi($this->settings($settings));
    }

    private function json(array $body): HttpResponse
    {
        return new HttpResponse(200, json_encode($body), ['content-type' => 'application/json'], 'https://x', 'GET');
    }

    private function text(string $body): HttpResponse
    {
        return new HttpResponse(200, $body, ['content-type' => 'text/plain'], 'https://x', 'GET');
    }

    /**
     * @param HttpResponse[] $responses
     */
    private function respondWith(HttpResponse ...$responses): void
    {
        $this->responses = $responses;
    }

    private function lastRequest(): array
    {
        self::assertNotEmpty($this->requests, 'no request was sent');

        return $this->requests[array_key_last($this->requests)];
    }

    // ---- конструктор и URL ----

    public function testUsesLiveApiUrlWhenTestModeIsOff(): void
    {
        $this->respondWith($this->json([]));

        $this->api()->intakeGet('u-1');

        self::assertSame(self::LIVE . 'intakes/u-1', $this->lastRequest()['url']);
    }

    public function testUsesSandboxApiUrlInTestMode(): void
    {
        $this->respondWith($this->json([]));

        $this->api(['test_mode' => true])->intakeGet('u-1');

        self::assertSame(Config::TEST_API_URL . 'intakes/u-1', $this->lastRequest()['url']);
    }

    public function testUsesApiUrlFromEnvironmentInTestMode(): void
    {
        $_ENV['CDEK_REST_API'] = 'https://env.api.test/v2/';
        $this->respondWith($this->json([]));

        $this->api(['test_mode' => true])->intakeGet('u-1');

        self::assertSame('https://env.api.test/v2/intakes/u-1', $this->lastRequest()['url']);
    }

    public function testResolvesSettingsViaFactoryWhenGivenAnInstanceIdOrNothing(): void
    {
        $method   = $this->settings();
        $factoryArgs = [];
        $method->shouldReceive('factory')->andReturnUsing(
            function (?int $instanceId = null) use (&$factoryArgs, $method) {
                $factoryArgs[] = $instanceId;

                return $method;
            },
        );

        new CdekApi(7);
        new CdekApi();

        self::assertSame([7, null], $factoryArgs);
    }

    // ---- fetchToken / authGetError ----

    public function testFetchTokenUsesSandboxCredentialsInTestMode(): void
    {
        $captured = null;
        $this->http->shouldReceive('processRequest')->andReturnUsing(
            function (string $url, string $method) use (&$captured) {
                $captured = [$url, $method];

                return $this->json(['access_token' => 'sandbox-token']);
            },
        );

        self::assertSame('sandbox-token', $this->api(['test_mode' => true])->fetchToken());

        self::assertSame('POST', $captured[1]);
        self::assertSame(
            Config::TEST_API_URL . 'oauth/token?' . http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => Config::TEST_CLIENT_ID,
                'client_secret' => Config::TEST_CLIENT_SECRET,
            ]),
            $captured[0],
        );
    }

    public function testFetchTokenUsesConfiguredCredentialsInLiveMode(): void
    {
        $url = null;
        $this->http->shouldReceive('processRequest')->andReturnUsing(
            function (string $requestUrl) use (&$url) {
                $url = $requestUrl;

                return $this->json(['access_token' => 'live-token']);
            },
        );

        self::assertSame('live-token', $this->api()->fetchToken());
        self::assertSame(
            self::LIVE . 'oauth/token?' . http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => 'my-id',
                'client_secret' => 'my-secret',
            ]),
            $url,
        );
    }

    public function testFetchTokenUsesConfiguredCredentialsWhenSandboxUrlIsOverriddenByEnvironment(): void
    {
        $_ENV['CDEK_REST_API'] = 'https://env.api.test/v2/';

        $url = null;
        $this->http->shouldReceive('processRequest')->andReturnUsing(
            function (string $requestUrl) use (&$url) {
                $url = $requestUrl;

                return $this->json(['access_token' => 't']);
            },
        );

        $this->api(['test_mode' => true])->fetchToken();

        self::assertStringStartsWith('https://env.api.test/v2/oauth/token?', $url);
        self::assertStringContainsString('client_id=my-id', $url);
        self::assertStringContainsString('client_secret=my-secret', $url);
    }

    public function testFetchTokenThrowsWithHostAndClientWhenAnswerHasNoAccessToken(): void
    {
        $this->http->shouldReceive('processRequest')->andReturn($this->json(['error' => 'invalid_client']));

        try {
            $this->api()->fetchToken();
            self::fail('LegacyAuthException expected');
        } catch (LegacyAuthException $e) {
            self::assertSame(
                ['error' => 'invalid_client', 'host' => 'api.cdek.ru', 'client' => 'my-id'],
                $e->getData(),
            );
        }
    }

    public function testFetchTokenWrapsTransportErrorIntoLegacyAuthException(): void
    {
        $this->http->shouldReceive('processRequest')->andThrow(
            new ApiException(['code' => 'timeout', 'nested' => ['a' => 1], 'status' => 504]),
        );

        try {
            $this->api()->fetchToken();
            self::fail('LegacyAuthException expected');
        } catch (LegacyAuthException $e) {
            self::assertSame(
                [
                    'code'   => 'timeout',
                    'nested' => ['a' => 1],
                    'status' => '504',
                    'host'   => 'api.cdek.ru',
                    'client' => 'my-id',
                ],
                $e->getData(),
            );
        }
    }

    public function testAuthGetErrorIsNullWhenTokenIsFetched(): void
    {
        $this->http->shouldReceive('processRequest')->andReturn($this->json(['access_token' => 't']));

        self::assertNull($this->api()->authGetError());
    }

    /**
     * @dataProvider authErrorProvider
     */
    public function testAuthGetErrorReturnsApiErrorCode(array $answer, string $expected): void
    {
        $this->http->shouldReceive('processRequest')->andReturn($this->json($answer));

        self::assertSame($expected, $this->api()->authGetError());
    }

    public static function authErrorProvider(): array
    {
        return [
            'code is preferred' => [['code' => 'unauthorized', 'error' => 'other'], 'unauthorized'],
            'error fallback'    => [['error' => 'invalid_client'], 'invalid_client'],
            'unknown'           => [['message' => 'boom'], 'unknown'],
        ];
    }

    // ---- расчёт ----

    public function testCalculateGetSendsMappedRequestAndReturnsTotalSum(): void
    {
        $this->respondWith($this->json(['total_sum' => '250.5']));

        $result = $this->api()->calculateGet([
            'type'        => 1,
            'from'        => ['code' => 270],
            'tariff_code' => 139,
            'to'          => ['city' => 'Москва'],
            'packages'    => ['weight' => 500],
            'ignored'     => 'x',
        ]);

        self::assertSame(250.5, $result);
        self::assertSame(
            [
                'url'    => self::LIVE . 'calculator/tariff',
                'method' => 'POST',
                'token'  => self::TOKEN,
                'data'   => [
                    'type'          => 1,
                    'from_location' => ['code' => 270],
                    'tariff_code'   => 139,
                    'to_location'   => ['city' => 'Москва'],
                    'packages'      => ['weight' => 500],
                    'services'      => [],
                ],
            ],
            $this->lastRequest(),
        );
    }

    public function testCalculateGetPassesServicesAndReturnsNullWithoutTotalSum(): void
    {
        $this->respondWith($this->json(['errors' => []]));

        $result = $this->api()->calculateGet([
            'type'        => 1,
            'from'        => [],
            'tariff_code' => 139,
            'to'          => [],
            'packages'    => [],
            'services'    => [['code' => 'INSURANCE', 'parameter' => 1000]],
        ]);

        self::assertNull($result);
        self::assertSame([['code' => 'INSURANCE', 'parameter' => 1000]], $this->lastRequest()['data']['services']);
    }

    public function testCalculateListSendsMappedRequestAndReturnsAnswer(): void
    {
        $answer = ['tariff_codes' => [['tariff_code' => 139]]];
        $this->respondWith($this->json($answer));

        $result = $this->api()->calculateList([
            'type'     => 2,
            'from'     => ['code' => 270],
            'to'       => ['city' => 'Москва'],
            'packages' => ['weight' => 500],
        ]);

        self::assertSame($answer, $result);
        self::assertSame(
            [
                'url'    => self::LIVE . 'calculator/tarifflist',
                'method' => 'POST',
                'token'  => self::TOKEN,
                'data'   => [
                    'type'          => 2,
                    'from_location' => ['code' => 270],
                    'to_location'   => ['city' => 'Москва'],
                    'packages'      => ['weight' => 500],
                ],
            ],
            $this->lastRequest(),
        );
    }

    // ---- города ----

    public function testCityCodeGetSearchesWithPostcodeAndReturnsCodeAsString(): void
    {
        $this->respondWith($this->json([['code' => 270]]));

        self::assertSame('270', $this->api()->cityCodeGet('Новосибирск', '630000'));
        self::assertCount(1, $this->requests);
        self::assertSame(
            [
                'url'    => self::LIVE . 'location/cities',
                'method' => 'GET',
                'token'  => self::TOKEN,
                'data'   => ['city' => 'Новосибирск', 'postal_code' => '630000'],
            ],
            $this->lastRequest(),
        );
    }

    public function testCityCodeGetRetriesWithoutPostcodeWhenNothingFound(): void
    {
        $this->respondWith($this->json([]), $this->json([['code' => 44]]));

        self::assertSame('44', $this->api()->cityCodeGet('Москва', '999999'));
        self::assertCount(2, $this->requests);
        self::assertSame(['city' => 'Москва', 'postal_code' => '999999'], $this->requests[0]['data']);
        self::assertSame(['city' => 'Москва', 'postal_code' => null], $this->requests[1]['data']);
    }

    public function testCityCodeGetAppendsMicrodistrictForKlimovsk(): void
    {
        $this->respondWith($this->json([['code' => 1]]));

        $this->api()->cityCodeGet('Климовск', '142180');

        self::assertSame('Климовск микрорайон', $this->lastRequest()['data']['city']);
    }

    public function testCityCodeGetReturnsNullWhenApiFails(): void
    {
        $this->transportError = new ApiException([]);

        self::assertNull($this->api()->cityCodeGet('Москва', '101000'));
    }

    public function testCityGetSendsCountryFilterAndReturnsFirstCity(): void
    {
        $this->respondWith($this->json([['code' => 270, 'city' => 'Новосибирск'], ['code' => 1]]));

        self::assertSame(['code' => 270, 'city' => 'Новосибирск'], $this->api()->cityGet('Новосибирск', '630000', 'RU'));
        self::assertSame(
            ['city' => 'Новосибирск', 'postal_code' => '630000', 'country_codes' => ['RU']],
            $this->lastRequest()['data'],
        );
    }

    public function testCityGetOmitsCountryFilterWhenCountryIsNotGiven(): void
    {
        $this->respondWith($this->json([['code' => 1]]));

        $this->api()->cityGet('Москва', '101000');

        self::assertNull($this->lastRequest()['data']['country_codes']);
    }

    public function testCityGetReturnsNullForEmptyCityEntry(): void
    {
        $this->respondWith($this->json([[]]));

        self::assertNull($this->api()->cityGet('Нигде', '000000'));
    }

    public function testCityGetAppendsMicrodistrictForKlimovsk(): void
    {
        $this->respondWith($this->json([['code' => 1]]));

        $this->api()->cityGet('климовск', '142180');

        self::assertSame('климовск микрорайон', $this->lastRequest()['data']['city']);
    }

    public function testCityGetReturnsNullWhenApiFails(): void
    {
        $this->transportError = new ApiException([]);

        self::assertNull($this->api()->cityGet('Москва', '101000'));
    }

    public function testCitySuggestSendsNameAndCountry(): void
    {
        $this->respondWith($this->json([['city' => 'Москва']]));

        self::assertSame([['city' => 'Москва']], $this->api()->citySuggest('Моск', 'RU'));
        self::assertSame(
            [
                'url'    => self::LIVE . 'location/suggest/cities',
                'method' => 'GET',
                'token'  => self::TOKEN,
                'data'   => ['name' => 'Моск', 'country_code' => 'RU'],
            ],
            $this->lastRequest(),
        );
    }

    // ---- ПВЗ и файлы ----

    public function testOfficeGetReturnsFirstOfficeOrNull(): void
    {
        $this->respondWith($this->json([['code' => 'NSK17'], ['code' => 'NSK18']]), $this->json([]));

        $api = $this->api();

        self::assertSame(['code' => 'NSK17'], $api->officeGet('NSK17'));
        self::assertSame(
            [
                'url'    => self::LIVE . 'deliverypoints',
                'method' => 'GET',
                'token'  => self::TOKEN,
                'data'   => ['code' => 'NSK17'],
            ],
            $this->lastRequest(),
        );
        self::assertNull($api->officeGet('NOPE'));
    }

    public function testOfficeListRawReturnsUndecodedBody(): void
    {
        $this->respondWith($this->text('[{"code":"NSK17"}]'));

        self::assertSame('[{"code":"NSK17"}]', $this->api()->officeListRaw('270'));
        self::assertSame(['city_code' => '270'], $this->lastRequest()['data']);
    }

    public function testFileGetRawDownloadsFileByLinkWithToken(): void
    {
        $this->respondWith($this->text('PDF-BYTES'));

        self::assertSame('PDF-BYTES', $this->api()->fileGetRaw('https://cdn.test/waybill.pdf'));
        self::assertSame(
            ['url' => 'https://cdn.test/waybill.pdf', 'method' => 'GET', 'token' => self::TOKEN, 'data' => null],
            $this->lastRequest(),
        );
    }

    // ---- заявки на вызов курьера ----

    public function testIntakeCreatePostsParamsAndReturnsResponse(): void
    {
        $response = $this->json(['entity' => ['uuid' => 'i-1']]);
        $this->respondWith($response);

        self::assertSame($response, $this->api()->intakeCreate(['cdek_number' => '777']));
        self::assertSame(
            [
                'url'    => self::LIVE . 'intakes',
                'method' => 'POST',
                'token'  => self::TOKEN,
                'data'   => ['cdek_number' => '777'],
            ],
            $this->lastRequest(),
        );
    }

    public function testIntakeDeleteSendsDeleteRequest(): void
    {
        $this->respondWith($this->json([]));

        $this->api()->intakeDelete('i-1');

        self::assertSame(
            ['url' => self::LIVE . 'intakes/i-1', 'method' => 'DELETE', 'token' => self::TOKEN, 'data' => null],
            $this->lastRequest(),
        );
    }

    public function testIntakeGetReturnsEntity(): void
    {
        $this->respondWith($this->json(['entity' => ['uuid' => 'i-1', 'intake_number' => 9001]]));

        self::assertSame(['uuid' => 'i-1', 'intake_number' => 9001], $this->api()->intakeGet('i-1'));
        self::assertSame('GET', $this->lastRequest()['method']);
    }

    // ---- заказы ----

    public function testOrderCreatePostsParamsAndReturnsUuid(): void
    {
        $this->respondWith($this->json(['entity' => ['uuid' => 'o-1']]));

        self::assertSame('o-1', $this->api()->orderCreate(['type' => 1]));
        self::assertSame(
            ['url' => self::LIVE . 'orders/', 'method' => 'POST', 'token' => self::TOKEN, 'data' => ['type' => 1]],
            $this->lastRequest(),
        );
    }

    public function testOrderCreateReturnsNullWithoutEntity(): void
    {
        $this->respondWith($this->json(['requests' => []]));

        self::assertNull($this->api()->orderCreate(['type' => 1]));
    }

    public function testOrderDeleteSendsDeleteAndReturnsAnswer(): void
    {
        $this->respondWith($this->json(['entity' => ['uuid' => 'o-1']]));

        self::assertSame(['entity' => ['uuid' => 'o-1']], $this->api()->orderDelete('o-1'));
        self::assertSame(
            ['url' => self::LIVE . 'orders/o-1', 'method' => 'DELETE', 'token' => self::TOKEN, 'data' => null],
            $this->lastRequest(),
        );
    }

    public function testOrderGetFetchesByUuid(): void
    {
        $response = $this->json(['entity' => []]);
        $this->respondWith($response);

        self::assertSame($response, $this->api()->orderGet('o-1'));
        self::assertSame(self::LIVE . 'orders/o-1', $this->lastRequest()['url']);
    }

    public function testOrderGetByNumberEncodesCdekNumberIntoQuery(): void
    {
        $response = $this->json(['entity' => []]);
        $this->respondWith($response);

        self::assertSame($response, $this->api()->orderGetByNumber('12 34&5'));
        self::assertSame(self::LIVE . 'orders?cdek_number=12+34%265', $this->lastRequest()['url']);
        self::assertSame('GET', $this->lastRequest()['method']);
    }

    // ---- печатные формы ----

    public function testWaybillCreateReturnsPrintUuidOrNull(): void
    {
        $this->respondWith($this->json(['entity' => ['uuid' => 'w-1']]), $this->json([]));

        $api = $this->api();

        self::assertSame('w-1', $api->waybillCreate('777'));
        self::assertSame(
            [
                'url'    => self::LIVE . 'print/orders/',
                'method' => 'POST',
                'token'  => self::TOKEN,
                'data'   => ['orders' => ['cdek_number' => '777']],
            ],
            $this->lastRequest(),
        );
        self::assertNull($api->waybillCreate('777'));
    }

    public function testWaybillGetReturnsEntity(): void
    {
        $this->respondWith($this->json(['entity' => ['url' => 'https://cdn.test/w.pdf']]));

        self::assertSame(['url' => 'https://cdn.test/w.pdf'], $this->api()->waybillGet('w-1'));
        self::assertSame(self::LIVE . 'print/orders/w-1', $this->lastRequest()['url']);
    }

    /**
     * @dataProvider barcodeFormatProvider
     */
    public function testBarcodeCreateUsesConfiguredFormat(int $optionIndex, string $expectedFormat): void
    {
        $this->respondWith($this->json(['entity' => ['uuid' => 'b-1']]));

        $method = $this->settings();
        $method->shouldReceive('get_option')->with('barcode_format', 0)->andReturn((string)$optionIndex);

        self::assertSame('b-1', (new CdekApi($method))->barcodeCreate('777'));
        self::assertSame(
            [
                'url'    => self::LIVE . 'print/barcodes',
                'method' => 'POST',
                'token'  => self::TOKEN,
                'data'   => ['orders' => ['cdek_number' => '777'], 'format' => $expectedFormat],
            ],
            $this->lastRequest(),
        );
    }

    public static function barcodeFormatProvider(): array
    {
        return ['A4' => [0, 'A4'], 'A5' => [1, 'A5'], 'A6' => [2, 'A6'], 'A7' => [3, 'A7']];
    }

    public function testBarcodeCreateReturnsNullWithoutEntity(): void
    {
        $this->respondWith($this->json([]));

        $method = $this->settings();
        $method->shouldReceive('get_option')->andReturn('0');

        self::assertNull((new CdekApi($method))->barcodeCreate('777'));
    }

    public function testBarcodeGetReturnsEntity(): void
    {
        $this->respondWith($this->json(['entity' => ['format' => 'A6', 'url' => 'https://cdn.test/b.pdf']]));

        self::assertSame(['format' => 'A6', 'url' => 'https://cdn.test/b.pdf'], $this->api()->barcodeGet('b-1'));
        self::assertSame(self::LIVE . 'print/barcodes/b-1', $this->lastRequest()['url']);
    }
}
