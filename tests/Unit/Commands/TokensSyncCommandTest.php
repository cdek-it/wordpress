<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Commands;

use Cdek\Commands\TokensSyncCommand;
use Cdek\Helpers\Cache;
use Cdek\Tests\TestCase;
use Mockery;

final class TokensSyncCommandTest extends TestCase
{
    protected string $testUrlAPI = 'https://api.test.test';
    protected string $testUrlAPISandbox = 'https://api-sandbox.test.test';

    private static function buildToken(array $footer): string
    {
        return 'header.payload.' . base64_encode(json_encode($footer));
    }

    public function testNewCreatesInstance(): void
    {
        self::assertInstanceOf(TokensSyncCommand::class, TokensSyncCommand::new());
    }

    public function testGetTokenFooterArrayDecodesFooterSegment(): void
    {
        $token = self::buildToken(['endpoint' => $this->testUrlAPI, 'exp' => 12345]);

        self::assertSame(
            ['endpoint' => $this->testUrlAPI, 'exp' => 12345],
            TokensSyncCommand::getTokenFooterArray($token)
        );
    }

    public function testGetTokenFooterArrayReturnsEmptyArrayForMalformedToken(): void
    {
        self::assertSame([], TokensSyncCommand::getTokenFooterArray('not-a-valid-token'));
    }

    public function testInvokePutsTokensAndResolvedEndpointsIntoCache(): void
    {
        $tokenA = self::buildToken(['endpoint' => $this->testUrlAPI]);
        $tokenB = self::buildToken(['endpoint' => $this->testUrlAPISandbox]);

        $tokens = [
            'client_a' => $tokenA,
            'client_b' => $tokenB,
        ];

        $cachedPayload = $this->interceptCachePut();

        (TokensSyncCommand::new())($tokens);

        self::assertSame(
            [
                'tokens'    => $tokens,
                'endpoints' => [
                    'client_a' => $this->testUrlAPI,
                    'client_b' => $this->testUrlAPISandbox,
                ],
            ],
            $cachedPayload()
        );
    }

    public function testInvokeFallsBackToNullEndpointWhenFooterEndpointIsFalsy(): void
    {
        $token = self::buildToken(['endpoint' => null]);

        $tokens = ['client_a' => $token];

        $cachedPayload = $this->interceptCachePut();

        (TokensSyncCommand::new())($tokens);

        self::assertSame(
            [
                'tokens'    => $tokens,
                'endpoints' => ['client_a' => null],
            ],
            $cachedPayload()
        );
    }

    public function testInvokeHandlesEmptyTokenList(): void
    {
        $cachedPayload = $this->interceptCachePut();

        (TokensSyncCommand::new())([]);

        self::assertSame(
            [
                'tokens'    => [],
                'endpoints' => [],
            ],
            $cachedPayload()
        );
    }

    private function interceptCachePut(): callable
    {
        $captured = null;

        Mockery::mock('alias:' . Cache::class)
            ->shouldReceive('put')
            ->once()
            ->with(Mockery::on(static function (array $payload) use (&$captured): bool {
                $captured = $payload;

                return true;
            }));

        return static function () use (&$captured) {
            return $captured;
        };
    }
}
