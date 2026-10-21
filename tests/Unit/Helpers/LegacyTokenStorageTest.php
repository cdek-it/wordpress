<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Helpers;

use Brain\Monkey\Functions;
use Cdek\CdekApi;
use Cdek\Exceptions\External\LegacyAuthException;
use Cdek\Helpers\LegacyTokenStorage;
use Cdek\Loader;
use Cdek\ShippingMethod;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use ReflectionProperty;

final class LegacyTokenStorageTest extends TestCase
{
    private const CLIENT_ID = 'client-id-123';
    private const CIPHER    = 'AES-256-CBC';

    private int $factoryCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factoryCalls = 0;
        $this->resetStaticCache();

        Functions\when('esc_html__')->returnArg();

        $property = new ReflectionProperty(Loader::class, 'pluginName');
        $property->setAccessible(true);

        if (!$property->isInitialized(null)) {
            $property->setValue(null, 'CDEK');
        }
    }

    protected function tearDown(): void
    {
        $this->resetStaticCache();

        parent::tearDown();
    }

    private function resetStaticCache(): void
    {
        $this->setStatic('token', '');
        $this->setStatic('tokenExp', 0);
    }

    /**
     * @param string|int $value
     */
    private function setStatic(string $name, $value): void
    {
        $property = new ReflectionProperty(LegacyTokenStorage::class, $name);
        $property->setAccessible(true);
        $property->setValue(null, $value);
    }

    private function jwt(int $exp): string
    {
        $encode = static fn(string $json): string => rtrim(strtr(base64_encode($json), '+/', '-_'), '=');

        return $encode('{"alg":"HS256"}') . '.' . $encode(json_encode(['exp' => $exp])) . '.signature';
    }

    private function encrypt(string $token, string $clientId = self::CLIENT_ID): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));

        return base64_encode($iv . openssl_encrypt($token, self::CIPHER, $clientId, 0, $iv));
    }

    private function decrypt(string $stored, string $clientId = self::CLIENT_ID): string
    {
        $data   = base64_decode($stored);
        $ivSize = openssl_cipher_iv_length(self::CIPHER);

        return (string)openssl_decrypt(substr($data, $ivSize), self::CIPHER, $clientId, 0, substr($data, 0, $ivSize));
    }

    private function mockShippingMethod(?string $storedToken): MockInterface
    {
        $method            = Mockery::mock('alias:' . ShippingMethod::class);
        $method->token     = $storedToken;
        $method->client_id = self::CLIENT_ID;
        $method->shouldReceive('factory')->andReturnUsing(function () use ($method) {
            $this->factoryCalls++;

            return $method;
        });

        return $method;
    }

    private function mockApiFetchToken(string $token): void
    {
        Mockery::mock('overload:' . CdekApi::class)
               ->shouldReceive('fetchToken')
               ->once()
               ->andReturn($token);
    }

    public function testFlushCacheClearsStoredTokenSetting(): void
    {
        $method = $this->mockShippingMethod(null);
        $method->shouldReceive('update_option')->once()->with('token', null);

        LegacyTokenStorage::flushCache();

        $this->addToAssertionCount(1);
    }

    public function testReturnsBearerTokenFromMemoryCacheWithoutTouchingSettingsOrApi(): void
    {
        $this->setStatic('token', 'cached-token');
        $this->setStatic('tokenExp', time() + 3600);

        self::assertSame('Bearer cached-token', (new LegacyTokenStorage())->getToken());
    }

    public function testReadsValidEncryptedTokenFromSettingsAndCachesIt(): void
    {
        $token  = $this->jwt(time() + 3600);
        $method = $this->mockShippingMethod($this->encrypt($token));
        $method->shouldReceive('update_option')->never();

        $storage = new LegacyTokenStorage();

        self::assertSame("Bearer $token", $storage->getToken());

        $callsAfterFirst = $this->factoryCalls;
        self::assertSame("Bearer $token", $storage->getToken());
        self::assertSame($callsAfterFirst, $this->factoryCalls);
    }

    /**
     * @dataProvider unusableStoredTokenProvider
     */
    public function testFetchesAndStoresNewTokenWhenSettingsTokenIsUnusable(callable $storedToken): void
    {
        $fresh  = $this->jwt(time() + 7200);
        $stored = $storedToken($this);

        $method = $this->mockShippingMethod($stored);
        $method->shouldReceive('update_option')
               ->once()
               ->withArgs(function (string $key, string $value) use ($fresh): bool {
                   return $key === 'token' && $this->decrypt($value) === $fresh;
               });
        $this->mockApiFetchToken($fresh);

        self::assertSame("Bearer $fresh", (new LegacyTokenStorage())->getToken());
    }

    public static function unusableStoredTokenProvider(): array
    {
        return [
            'not set'                    => [static fn() => null],
            'empty'                      => [static fn() => ''],
            'expired'                    => [static fn(self $t) => $t->encrypt($t->jwt(time() - 60))],
            'encrypted for other client' => [static fn(self $t) => $t->encrypt($t->jwt(time() + 3600), 'other-client')],
            'garbage'                    => [static fn() => base64_encode('not an encrypted token')],
            'payload is not json'        => [static fn(self $t) => $t->encrypt('a.' . base64_encode('nope') . '.c')],
        ];
    }

    public function testExpiredMemoryCacheIsIgnored(): void
    {
        $this->setStatic('token', 'stale-token');
        $this->setStatic('tokenExp', time() - 1);

        $token  = $this->jwt(time() + 3600);
        $method = $this->mockShippingMethod($this->encrypt($token));
        $method->shouldReceive('update_option')->never();

        self::assertSame("Bearer $token", (new LegacyTokenStorage())->getToken());
    }

    public function testUpdateTokenStoresEncryptedTokenAndKeepsItInMemory(): void
    {
        $fresh = $this->jwt(time() + 7200);

        $stored = null;
        $method = $this->mockShippingMethod(null);
        $method->shouldReceive('update_option')->once()->andReturnUsing(
            static function (string $key, string $value) use (&$stored): bool {
                $stored = [$key, $value];

                return true;
            },
        );
        $this->mockApiFetchToken($fresh);

        $storage = new LegacyTokenStorage();

        self::assertSame($fresh, $storage->updateToken());
        self::assertSame('token', $stored[0]);
        self::assertNotSame($fresh, $stored[1]);
        self::assertSame($fresh, $this->decrypt($stored[1]));

        $callsAfterUpdate = $this->factoryCalls;
        self::assertSame("Bearer $fresh", $storage->getToken());
        self::assertSame($callsAfterUpdate, $this->factoryCalls);
    }

    public function testPropagatesLegacyAuthExceptionWhenTokenCannotBeFetched(): void
    {
        $this->mockShippingMethod(null);

        Mockery::mock('overload:' . CdekApi::class)
               ->shouldReceive('fetchToken')
               ->once()
               ->andThrow(new LegacyAuthException([]));

        $this->expectException(LegacyAuthException::class);

        (new LegacyTokenStorage())->getToken();
    }
}
