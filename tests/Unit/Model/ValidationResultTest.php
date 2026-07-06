<?php

declare(strict_types=1);

namespace Cdek\Tests\Unit\Model;

use Cdek\Model\ValidationResult;
use Cdek\Tests\TestCase;

final class ValidationResultTest extends TestCase
{
    public function testConstructorSetsStateAndMessage(): void
    {
        $result = new ValidationResult(true, 'all good');

        self::assertTrue($result->state);
        self::assertSame('all good', $result->message);
    }

    public function testConstructorDefaultsMessageToEmptyString(): void
    {
        $result = new ValidationResult(false);

        self::assertFalse($result->state);
        self::assertSame('', $result->message);
    }

    public function testStateReturnsTrueWhenValid(): void
    {
        $result = new ValidationResult(true, 'ok');

        self::assertTrue($result->state());
    }

    public function testStateReturnsFalseWhenInvalid(): void
    {
        $result = new ValidationResult(false, 'error');

        self::assertFalse($result->state());
    }

    public function testResponseReturnsStateAndMessage(): void
    {
        $result = new ValidationResult(false, 'validation failed');

        self::assertSame(
            ['state' => false, 'message' => 'validation failed'],
            $result->response(),
        );
    }

    public function testResponseReturnsEmptyMessageByDefault(): void
    {
        $result = new ValidationResult(true);

        self::assertSame(['state' => true, 'message' => ''], $result->response());
    }
}
