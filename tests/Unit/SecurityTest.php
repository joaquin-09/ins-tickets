<?php

declare(strict_types=1);

namespace InsTickets\Tests\Unit;

use InsTickets\Core\Csrf;
use InsTickets\Core\Session;
use InsTickets\Core\View;
use InsTickets\Services\LoginPolicy;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    public function testCsrfRejectsAbsentWrongAndArrayTokens(): void
    {
        $_SESSION = [];
        self::assertFalse(Csrf::valid(null));
        $token = Csrf::token();
        self::assertSame($token, Csrf::token());
        self::assertTrue(Csrf::valid($token));
        self::assertFalse(Csrf::valid('invalido'));
        self::assertFalse(Csrf::valid([$token]));
    }

    public function testCsrfFromAnotherSessionFails(): void
    {
        $_SESSION = [];
        $old = Csrf::token();
        $_SESSION = [];
        self::assertNotSame($old, Csrf::token());
        self::assertFalse(Csrf::valid($old));
    }

    public function testSessionIdleBoundary(): void
    {
        self::assertFalse(Session::expired(100, 1899));
        self::assertTrue(Session::expired(100, 1900));
        self::assertTrue(Session::expired(100, 2000));
    }

    public function testLoginLimitBoundary(): void
    {
        self::assertFalse(LoginPolicy::blocked(4));
        self::assertTrue(LoginPolicy::blocked(5));
        self::assertTrue(LoginPolicy::blocked(6));
    }

    public function testLoginKeyNormalizesEmailAndSeparatesIps(): void
    {
        $key = LoginPolicy::key(' Ana@Nexoti.test ', '127.0.0.1', 'secreto');
        self::assertSame($key, LoginPolicy::key('ana@nexoti.test', '127.0.0.1', 'secreto'));
        self::assertNotSame($key, LoginPolicy::key('ana@nexoti.test', '127.0.0.2', 'secreto'));
        self::assertNotSame($key, LoginPolicy::key('ana@nexoti.test', '127.0.0.1', 'otro'));
        self::assertSame(64, strlen($key));
        self::assertStringNotContainsString('ana', $key);
    }

    public function testStoredTextIsEscapedForHtmlAndAttributes(): void
    {
        $escaped = View::escape('<script>alert("x")</script>');
        self::assertStringNotContainsString('<script>', $escaped);
        self::assertStringContainsString('&lt;script&gt;', $escaped);
        self::assertStringContainsString('&quot;', $escaped);
    }
}
