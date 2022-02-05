<?php

declare(strict_types=1);

namespace Jelly\Tests\Unit\Middlewares;

use Jelly\Middlewares\UncaughtExceptionSafeguard;
use Jelly\Tests\Fixtures\BrokenHandler;
use Jelly\Tests\Fixtures\HelloHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class UncaughtExceptionSafeguardTest extends TestCase
{
    private string $errorLog;

    protected function setUp(): void
    {
        $this->errorLog = ini_set('error_log', '/dev/null');
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->errorLog);
    }

    public function testSuccessfulRun(): void
    {
        $sut = new UncaughtExceptionSafeguard(new Psr17Factory(), new Psr17Factory(), true);

        $response = $sut->process(new ServerRequest('GET', '/hello'), new HelloHandler());

        self::assertSame(200, $response->getStatusCode());
    }

    public function testExceptionInProdMode(): void
    {
        $sut = new UncaughtExceptionSafeguard(new Psr17Factory(), new Psr17Factory(), true);

        $response = $sut->process(new ServerRequest('GET', '/hello'), new BrokenHandler());

        self::assertSame(500, $response->getStatusCode());
        self::assertFalse($response->hasHeader('Content-Type'));
    }

    public function testExceptionInDevelopmentMode(): void
    {
        $sut = new UncaughtExceptionSafeguard(new Psr17Factory(), new Psr17Factory(), false);

        $response = $sut->process(new ServerRequest('GET', '/hello'), $handler = new BrokenHandler());

        self::assertSame(500, $response->getStatusCode());
        self::assertTrue($response->hasHeader('Content-Type'));
        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame((string) $handler->exception, (string) $response->getBody());
    }
}
