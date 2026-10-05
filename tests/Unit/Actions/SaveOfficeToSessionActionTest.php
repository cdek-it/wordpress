<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Actions;

use Brain\Monkey\Functions;
use Cdek\Actions\SaveOfficeToSessionAction;
use Cdek\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;

final class SaveOfficeToSessionActionTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_POST['code']);

        parent::tearDown();
    }

    private function mockSession(?MockInterface $session): void
    {
        $wc          = Mockery::mock();
        $wc->session = $session;

        Functions\when('WC')->justReturn($wc);
    }

    public function testDoesNothingWhenCodeIsNotPosted(): void
    {
        unset($_POST['code']);

        (new SaveOfficeToSessionAction())();

        $this->addToAssertionCount(1);
    }

    public function testSavesSanitizedOfficeCodeToSessionAndSendsSuccess(): void
    {
        $_POST['code'] = ' MSK123 ';

        Functions\expect('sanitize_text_field')->once()->with(' MSK123 ')->andReturn('MSK123');

        $session = Mockery::mock();
        $session->shouldReceive('set')->once()->with('official_cdek_office_code', 'MSK123');
        $this->mockSession($session);

        Functions\expect('wp_send_json_success')->once();
        Functions\expect('wp_send_json_error')->never();

        (new SaveOfficeToSessionAction())();

        $this->addToAssertionCount(1);
    }

    public function testSendsErrorWhenSessionIsNull(): void
    {
        $_POST['code'] = 'MSK123';

        Functions\when('sanitize_text_field')->returnArg();
        $this->mockSession(null);

        Functions\expect('wp_send_json_error')->once();
        Functions\expect('wp_send_json_success')->never();

        (new SaveOfficeToSessionAction())();

        $this->addToAssertionCount(1);
    }

    public function testSendsErrorWhenSessionThrows(): void
    {
        $_POST['code'] = 'MSK123';

        Functions\when('sanitize_text_field')->returnArg();

        $session = Mockery::mock();
        $session->shouldReceive('set')->once()->andThrow(new \RuntimeException('boom'));
        $this->mockSession($session);

        Functions\expect('wp_send_json_error')->once();
        Functions\expect('wp_send_json_success')->never();

        (new SaveOfficeToSessionAction())();

        $this->addToAssertionCount(1);
    }
}
