<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit;

use Brain\Monkey\Functions;
use Cdek\Config;
use Cdek\CoreApi;
use Cdek\Exceptions\External\ApiException;
use Cdek\Exceptions\External\CoreAuthException;
use Cdek\Exceptions\ShopRegistrationException;
use Cdek\Helpers\Tokens;
use Cdek\Loader;
use Cdek\Model\TaskResult;
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
final class CoreApiTest extends TestCase
{
    private const BASE = 'https://core.test/api';

    private MockInterface $http;
    private MockInterface $tokens;

    /** @var mixed */
    private $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEnv = $_ENV['CDEK_CORE_API'] ?? null;
        unset($_ENV['CDEK_CORE_API']);

        Functions\when('esc_html__')->returnArg();

        $property = new ReflectionProperty(Loader::class, 'pluginName');
        $property->setAccessible(true);
        $property->setValue(null, 'CDEK');

        $this->http   = Mockery::mock('alias:' . HttpClient::class);
        $this->tokens = Mockery::mock('alias:' . Tokens::class);
    }

    protected function tearDown(): void
    {
        if ($this->originalEnv === null) {
            unset($_ENV['CDEK_CORE_API']);
        } else {
            $_ENV['CDEK_CORE_API'] = $this->originalEnv;
        }

        parent::tearDown();
    }

    private function endpoint(?string $base): void
    {
        $this->tokens->shouldReceive('getEndpoint')->with('wordpress')->andReturn($base);
    }

    private function token(?string $token): void
    {
        $this->tokens->shouldReceive('get')->with('wordpress')->andReturn($token);
    }

    private function response(array $data = [], array $headers = []): MockInterface
    {
        $response = Mockery::mock();
        $response->shouldReceive('data')->andReturn($data);
        $response->shouldReceive('getHeaders')->andReturn($headers);

        return $response;
    }

    // ---- токен и endpoint ----

    public function testHasTokenReflectsTokenAvailability(): void
    {
        $this->tokens->shouldReceive('get')->with('wordpress')->andReturn('Bearer abc', null);

        $api = new CoreApi();

        self::assertTrue($api->hasToken());
        self::assertFalse($api->hasToken());
    }

    public function testUsesTokenTypePassedToConstructor(): void
    {
        $this->tokens->shouldReceive('getEndpoint')->with('sandbox')->andReturn(self::BASE);
        $this->tokens->shouldReceive('get')->with('sandbox')->andReturn('Bearer sb');
        $this->http->shouldReceive('sendJsonRequest')
                   ->once()
                   ->with(self::BASE . '/orders/7', 'GET', 'Bearer sb')
                   ->andReturn($this->response(['id' => 7]));

        self::assertSame(['id' => 7], (new CoreApi('sandbox'))->orderGet(7));
    }

    public function testStrictEndpointThrowsAuthExceptionWhenEndpointIsUnknown(): void
    {
        $this->endpoint(null);
        $this->token('Bearer abc');
        $this->http->shouldReceive('sendJsonRequest')->never();

        $this->expectException(CoreAuthException::class);

        (new CoreApi())->orderGet(1);
    }

    public function testThrowsAuthExceptionWhenTokenIsMissing(): void
    {
        $this->endpoint(self::BASE);
        $this->token(null);
        $this->http->shouldReceive('sendJsonRequest')->never();

        $this->expectException(CoreAuthException::class);

        (new CoreApi())->orderGet(1);
    }

    public function testKeyringFetchUsesKnownEndpointWithoutToken(): void
    {
        $this->endpoint(self::BASE);
        $this->http->shouldReceive('sendJsonRequest')
                   ->once()
                   ->with(self::BASE . '/.well-known/key', 'GET', null)
                   ->andReturn($this->response(['k1' => 'public-key']));

        self::assertSame(['k1' => 'public-key'], (new CoreApi())->keyringFetch());
    }

    public function testKeyringFetchFallsBackToDefaultCoreUrl(): void
    {
        $this->endpoint(null);
        $this->http->shouldReceive('sendJsonRequest')
                   ->once()
                   ->with(Config::API_CORE_URL . 'cms/.well-known/key', 'GET', null)
                   ->andReturn($this->response([]));

        (new CoreApi())->keyringFetch();

        $this->addToAssertionCount(1);
    }

    public function testKeyringFetchPrefersCoreUrlFromEnvironment(): void
    {
        $_ENV['CDEK_CORE_API'] = 'https://env.core.test/';

        $this->endpoint(null);
        $this->http->shouldReceive('sendJsonRequest')
                   ->once()
                   ->with('https://env.core.test/cms/.well-known/key', 'GET', null)
                   ->andReturn($this->response([]));

        (new CoreApi())->keyringFetch();

        $this->addToAssertionCount(1);
    }

    public function testValidatePhoneReturnsNormalizedPhone(): void
    {
        $this->endpoint(self::BASE);
        $this->token('Bearer abc');
        $this->http->shouldReceive('sendJsonRequest')
                   ->once()
                   ->with(self::BASE . '/validate/phone', 'GET', 'Bearer abc', ['phone' => '8 999 123-45-67', 'country' => 'RU'])
                   ->andReturn($this->response(['phone' => '+79991234567']));

        self::assertSame('+79991234567', (new CoreApi())->validatePhone('8 999 123-45-67', 'RU'));
    }

    public function testValidatePhoneSendsNullCountryByDefault(): void
    {
        $this->endpoint(self::BASE);
        $this->token('Bearer abc');
        $this->http->shouldReceive('sendJsonRequest')
                   ->once()
                   ->with(self::BASE . '/validate/phone', 'GET', 'Bearer abc', ['phone' => '123', 'country' => null])
                   ->andReturn($this->response(['phone' => '+7123']));

        self::assertSame('+7123', (new CoreApi())->validatePhone('123'));
    }

    public function testTaskGetFetchesTaskById(): void
    {
        $this->endpoint(self::BASE);
        $this->token('Bearer abc');
        $this->http->shouldReceive('sendJsonRequest')
                   ->once()
                   ->with(self::BASE . '/tasks/t-1', 'GET', 'Bearer abc')
                   ->andReturn($this->response(['meta' => ['a' => 1]]));

        self::assertSame(['meta' => ['a' => 1]], (new CoreApi())->taskGet('t-1'));
    }

    public function testTaskListSendsCursorOnlyWhenGiven(): void
    {
        $this->endpoint(self::BASE);
        $this->token('Bearer abc');

        $first  = new HttpResponse(200, '{}', [], self::BASE . '/tasks', 'GET');
        $second = new HttpResponse(200, '{}', [], self::BASE . '/tasks', 'GET');

        $this->http->shouldReceive('sendJsonRequest')
                   ->once()
                   ->with(self::BASE . '/tasks', 'GET', 'Bearer abc', null)
                   ->andReturn($first);
        $this->http->shouldReceive('sendJsonRequest')
                   ->once()
                   ->with(self::BASE . '/tasks', 'GET', 'Bearer abc', ['cursor' => 'next-page'])
                   ->andReturn($second);

        $api = new CoreApi();

        self::assertSame($first, $api->taskList());
        self::assertSame($second, $api->taskList('next-page'));
    }

    public function testTaskResultCreatePutsStatusDataAndPaging(): void
    {
        $this->endpoint(self::BASE);
        $this->token('Bearer abc');
        $this->http->shouldReceive('sendJsonRequest')
                   ->once()
                   ->with(
                       self::BASE . '/tasks/t-1',
                       'PUT',
                       'Bearer abc',
                       ['status' => 'success', 'result' => ['orders' => ['1', '2']]],
                       ['X-Current-Page' => 2, 'X-Total-Pages' => 5],
                   )
                   ->andReturn($this->response());

        (new CoreApi())->taskResultCreate('t-1', new TaskResult('success', ['orders' => ['1', '2']], 2, 5));

        $this->addToAssertionCount(1);
    }

    public function testShopSyncRegistersShopAndReturnsEntityId(): void
    {
        $this->endpoint(null);
        $this->http->shouldReceive('sendJsonRequest')
                   ->once()
                   ->with(
                       Config::API_CORE_URL . 'cms/wordpress/shop',
                       'POST',
                       'Bearer legacy',
                       [
                           'name' => 'Shop',
                           'url'  => [
                               'rest'  => 'https://shop.test/wp-json/official_cdek/cb',
                               'home'  => 'https://shop.test',
                               'admin' => 'https://shop.test/wp-admin/',
                           ],
                       ],
                   )
                   ->andReturn($this->response([], ['x-entity-id' => 'shop-uuid']));

        self::assertSame(
            'shop-uuid',
            (new CoreApi())->shopSync(
                'Bearer legacy',
                'Shop',
                'https://shop.test/wp-json/official_cdek/cb',
                'https://shop.test',
                'https://shop.test/wp-admin/',
            ),
        );
    }

    public function testShopSyncThrowsRegistrationExceptionWhenEntityIdHeaderIsMissing(): void
    {
        $this->endpoint(null);
        $this->http->shouldReceive('sendJsonRequest')->once()->andReturn($this->response([], []));

        $this->expectException(ShopRegistrationException::class);

        (new CoreApi())->shopSync('Bearer legacy', 'Shop', 'r', 'h', 'a');
    }

    public function testShopSyncConvertsApiErrorToRegistrationException(): void
    {
        $this->endpoint(null);
        $this->http->shouldReceive('sendJsonRequest')->once()->andThrow(new ApiException([]));

        $this->expectException(ShopRegistrationException::class);

        (new CoreApi())->shopSync('Bearer legacy', 'Shop', 'r', 'h', 'a');
    }

    public function testShopTokensFetchReturnsTokens(): void
    {
        $this->endpoint(self::BASE);
        $this->http->shouldReceive('sendJsonRequest')
                   ->once()
                   ->with(self::BASE . '/shops/shop-uuid/tokens', 'POST', 'Bearer legacy')
                   ->andReturn($this->response(['wordpress' => 'tok']));

        self::assertSame(['wordpress' => 'tok'], (new CoreApi())->shopTokensFetch('Bearer legacy', 'shop-uuid'));
    }

    public function testShopTokensFetchConvertsApiErrorToAuthException(): void
    {
        $this->endpoint(null);
        $this->http->shouldReceive('sendJsonRequest')->once()->andThrow(new ApiException([]));

        $this->expectException(CoreAuthException::class);

        (new CoreApi())->shopTokensFetch('Bearer legacy', 'shop-uuid');
    }
}
