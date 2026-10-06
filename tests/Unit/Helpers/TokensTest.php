<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Helpers;

use Brain\Monkey\Functions;
use Cdek\Commands\TokensSyncCommand;
use Cdek\CoreApi;
use Cdek\Exceptions\External\CoreAuthException;
use Cdek\Exceptions\External\LegacyAuthException;
use Cdek\Exceptions\ShopRegistrationException;
use Cdek\Helpers\Cache;
use Cdek\Helpers\LegacyTokenStorage;
use Cdek\Helpers\Tokens;
use Cdek\Loader;
use Cdek\Tests\TestCase;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Keys\AsymmetricSecretKey;
use ParagonIE\Paseto\Protocol\Version4;
use ParagonIE\Paseto\Purpose;
use ReflectionProperty;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class TokensTest extends TestCase
{
    private const HOST = 'shop.test';

    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('esc_html__')->returnArg();
        Functions\when('rest_url')->alias(static fn(string $path = '') => 'https://' . self::HOST . '/wp-json/' . $path);
        Functions\when('wp_parse_url')->alias(static fn($url, $component = -1) => parse_url($url, $component));

        $property = new ReflectionProperty(Loader::class, 'pluginName');
        $property->setAccessible(true);
        $property->setValue(null, 'CDEK');
    }

    private function mockCache(): MockInterface
    {
        return Mockery::mock('alias:' . Cache::class);
    }

    private function request(?string $token): MockInterface
    {
        $request = Mockery::mock('WP_REST_Request');
        $request->shouldReceive('get_header')->with('x_auth_token')->andReturn($token);

        return $request;
    }

    private function signToken(
        AsymmetricSecretKey $key,
        ?string $kid,
        string $audience = self::HOST,
        string $expiresIn = '+1 hour',
        string $subject = 'sync'
    ): string {
        $builder = (new Builder())
            ->setKey($key)
            ->setVersion(new Version4())
            ->setPurpose(Purpose::public())
            ->setIssuedAt(new DateTimeImmutable('-2 hours'))
            ->setNotBefore(new DateTimeImmutable('-2 hours'))
            ->setExpiration(new DateTimeImmutable($expiresIn))
            ->setAudience($audience)
            ->setSubject($subject);

        if ($kid !== null) {
            $builder = $builder->setFooterArray(['kid' => $kid]);
        }

        return $builder->toString();
    }

    private function keyring(string $kid, AsymmetricSecretKey $key): array
    {
        return [$kid => $key->getPublicKey()->encode()];
    }

    // ---- checkIncomingRequest ----

    public function testRejectsRequestWithoutToken(): void
    {
        $this->mockCache()->shouldReceive('remember')->never();

        self::assertFalse(Tokens::checkIncomingRequest($this->request(null)));
        self::assertFalse(Tokens::checkIncomingRequest($this->request('')));
    }

    public function testRejectsTokenWithoutKeyIdInFooter(): void
    {
        $key = AsymmetricSecretKey::generate(new Version4());
        $this->mockCache()->shouldReceive('remember')->never();

        $token = $this->signToken($key, null);

        self::assertFalse(Tokens::checkIncomingRequest($this->request($token)));
    }

    public function testRejectsTokenWhoseKeyIdIsNotInKeyring(): void
    {
        $key = AsymmetricSecretKey::generate(new Version4());
        $this->mockCache()->shouldReceive('remember')->once()->andReturn($this->keyring('known', $key));

        $token = $this->signToken($key, 'unknown');

        self::assertFalse(Tokens::checkIncomingRequest($this->request($token)));
    }

    public function testAcceptsValidTokenAndPassesSubjectAsCommand(): void
    {
        $key = AsymmetricSecretKey::generate(new Version4());

        $this->mockCache()
             ->shouldReceive('remember')
             ->once()
             ->with('keyring', Mockery::type('Closure'))
             ->andReturn($this->keyring('k1', $key));

        $request = $this->request($this->signToken($key, 'k1', self::HOST, '+1 hour', 'tokens-sync'));
        $request->shouldReceive('set_param')->once()->with('command', 'tokens-sync');

        self::assertTrue(Tokens::checkIncomingRequest($request));
    }

    /**
     * @dataProvider invalidTokenProvider
     */
    public function testRejectsTokenThatFailsVerification(string $audience, string $expiresIn, bool $signedByOtherKey): void
    {
        $trusted = AsymmetricSecretKey::generate(new Version4());
        $signer  = $signedByOtherKey ? AsymmetricSecretKey::generate(new Version4()) : $trusted;

        $this->mockCache()->shouldReceive('remember')->once()->andReturn($this->keyring('k1', $trusted));

        $request = $this->request($this->signToken($signer, 'k1', $audience, $expiresIn));
        $request->shouldReceive('set_param')->never();

        self::assertFalse(Tokens::checkIncomingRequest($request));
    }

    public static function invalidTokenProvider(): array
    {
        return [
            'expired'          => [self::HOST, '-1 hour', false],
            'foreign audience' => ['evil.test', '+1 hour', false],
            'foreign key'      => [self::HOST, '+1 hour', true],
        ];
    }

    // ---- getEndpoint ----

    public function testGetEndpointReturnsEndpointForRequestedTokenType(): void
    {
        $this->mockCache()
             ->shouldReceive('get')
             ->with('endpoints')
             ->andReturn(['wordpress' => 'https://wp.api', 'other' => 'https://other.api']);

        self::assertSame('https://wp.api', Tokens::getEndpoint());
        self::assertSame('https://other.api', Tokens::getEndpoint('other'));
    }

    /**
     * @dataProvider missingEndpointProvider
     */
    public function testGetEndpointReturnsNullWhenEndpointIsMissing(?array $endpoints): void
    {
        $this->mockCache()->shouldReceive('get')->with('endpoints')->andReturn($endpoints);

        self::assertNull(Tokens::getEndpoint('wordpress'));
    }

    public static function missingEndpointProvider(): array
    {
        return [
            'nothing cached'     => [null],
            'other type only'    => [['other' => 'https://other.api']],
            'empty endpoint'     => [['wordpress' => '']],
        ];
    }

    // ---- get / tryExchangeLegacyToken ----

    public function testGetReturnsBearerTokenFromCacheWithoutExchange(): void
    {
        $this->mockCache()->shouldReceive('get')->once()->with('tokens')->andReturn(['wordpress' => 'abc']);
        self::assertSame('Bearer abc', Tokens::get('wordpress'));
    }

    public function testGetExchangesLegacyTokenWhenCacheIsEmptyAndReadsFreshTokens(): void
    {
        $this->mockCache()
             ->shouldReceive('get')
             ->with('tokens')
             ->twice()
             ->andReturn(null, ['wordpress' => 'fresh']);

        Mockery::mock('overload:' . LegacyTokenStorage::class)
               ->shouldReceive('getToken')
               ->once()
               ->andReturn('Bearer legacy');

        Functions\when('get_bloginfo')->justReturn('Shop');
        Functions\when('home_url')->justReturn('https://shop.test');
        Functions\when('admin_url')->justReturn('https://shop.test/wp-admin/');

        $api = Mockery::mock('overload:' . CoreApi::class);
        $api->shouldReceive('shopSync')
            ->once()
            ->with(
                'Bearer legacy',
                'Shop',
                'https://shop.test/wp-json/official_cdek/cb',
                'https://shop.test',
                'https://shop.test/wp-admin/',
            )
            ->andReturn('shop-uuid');
        $api->shouldReceive('shopTokensFetch')
            ->once()
            ->with('Bearer legacy', 'shop-uuid')
            ->andReturn(['wordpress' => 'fresh']);

        $sync = new class {
            public array $calls = [];

            public function __invoke(array $tokens): void
            {
                $this->calls[] = $tokens;
            }
        };
        Mockery::mock('alias:' . TokensSyncCommand::class)->shouldReceive('new')->once()->andReturn($sync);

        self::assertSame('Bearer fresh', Tokens::get('wordpress'));
        self::assertSame([['wordpress' => 'fresh']], $sync->calls);
    }

    public function testGetReturnsNullWhenLegacyTokenIsNotAvailable(): void
    {
        $this->mockCache()->shouldReceive('get')->with('tokens')->twice()->andReturn(null);

        Mockery::mock('overload:' . LegacyTokenStorage::class)
               ->shouldReceive('getToken')
               ->once()
               ->andThrow(new LegacyAuthException([]));

        self::assertNull(Tokens::get('wordpress'));
    }

    /**
     * @dataProvider registrationFailureProvider
     */
    public function testGetReturnsNullAndSkipsSyncWhenShopRegistrationFails(string $stage, string $errorClass): void
    {
        $error = new $errorClass([]);

        $this->mockCache()->shouldReceive('get')->with('tokens')->twice()->andReturn(null);

        Mockery::mock('overload:' . LegacyTokenStorage::class)->shouldReceive('getToken')->andReturn('Bearer legacy');

        Functions\when('get_bloginfo')->justReturn('Shop');
        Functions\when('home_url')->justReturn('https://shop.test');
        Functions\when('admin_url')->justReturn('https://shop.test/wp-admin/');

        $api = Mockery::mock('overload:' . CoreApi::class);

        if ($stage === 'sync') {
            $api->shouldReceive('shopSync')->once()->andThrow($error);
            $api->shouldReceive('shopTokensFetch')->never();
        } else {
            $api->shouldReceive('shopSync')->once()->andReturn('shop-uuid');
            $api->shouldReceive('shopTokensFetch')->once()->andThrow($error);
        }

        Mockery::mock('alias:' . TokensSyncCommand::class)->shouldReceive('new')->never();

        self::assertNull(Tokens::get('wordpress'));
    }

    public static function registrationFailureProvider(): array
    {
        return [
            'registration fails on sync'  => ['sync', ShopRegistrationException::class],
            'core auth fails on sync'     => ['sync', CoreAuthException::class],
            'registration fails on fetch' => ['fetch', ShopRegistrationException::class],
            'core auth fails on fetch'    => ['fetch', CoreAuthException::class],
        ];
    }
}
