<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Actions;

use Brain\Monkey\Functions;
use Cdek\Actions\EnsureShopSessionCookieAction;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;

final class EnsureShopSessionCookieActionTest extends TestCase
{
    private function mockShopConditionals(bool $isShop, bool $isProduct, bool $isCategory, bool $isTag): void
    {
        Functions\when('is_shop')->justReturn($isShop);
        Functions\when('is_product')->justReturn($isProduct);
        Functions\when('is_product_category')->justReturn($isCategory);
        Functions\when('is_product_tag')->justReturn($isTag);
    }

    private function mockSession(?MockInterface $session): void
    {
        $wc          = Mockery::mock();
        $wc->session = $session;

        Functions\when('WC')->justReturn($wc);
    }

    public function testDoesNothingWhenNotOnAShopPage(): void
    {
        $this->mockShopConditionals(false, false, false, false);

        // WC() не должен понадобиться - если экшен всё же дойдёт до него,
        // немокнутый вызов упадёт с BadMethodCallException.
        (new EnsureShopSessionCookieAction())();

        $this->addToAssertionCount(1);
    }

    public function testDoesNothingWhenSessionIsNull(): void
    {
        $this->mockShopConditionals(true, false, false, false);
        $this->mockSession(null);

        (new EnsureShopSessionCookieAction())();

        $this->addToAssertionCount(1);
    }

    public function testDoesNothingWhenSessionIsNotAWcSessionHandlerInstance(): void
    {
        $this->mockShopConditionals(true, false, false, false);

        // Сторонний обработчик сессии (не WC_Session_Handler) не имеет
        // has_session()/set_customer_session_cookie() - экшен не должен падать на нём.
        $this->mockSession(Mockery::mock());

        (new EnsureShopSessionCookieAction())();

        $this->addToAssertionCount(1);
    }

    public function testDoesNothingWhenSessionAlreadyExists(): void
    {
        $this->mockShopConditionals(true, false, false, false);

        $session = Mockery::mock('WC_Session_Handler');
        $session->shouldReceive('has_session')->once()->andReturn(true);
        $session->shouldNotReceive('set_customer_session_cookie');
        $this->mockSession($session);

        (new EnsureShopSessionCookieAction())();

        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider shopPageProvider
     */
    public function testForcesSessionCookieOnShopPagesWithoutExistingSession(
        bool $isShop,
        bool $isProduct,
        bool $isCategory,
        bool $isTag
    ): void {
        $this->mockShopConditionals($isShop, $isProduct, $isCategory, $isTag);

        $session = Mockery::mock('WC_Session_Handler');
        $session->shouldReceive('has_session')->once()->andReturn(false);
        $session->shouldReceive('set_customer_session_cookie')->once()->with(true);
        $this->mockSession($session);

        (new EnsureShopSessionCookieAction())();

        $this->addToAssertionCount(1);
    }

    public static function shopPageProvider(): array
    {
        return [
            'shop archive'      => [true, false, false, false],
            'single product'    => [false, true, false, false],
            'product category'  => [false, false, true, false],
            'product tag'       => [false, false, false, true],
        ];
    }
}
