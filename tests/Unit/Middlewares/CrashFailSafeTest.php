<?php

declare(strict_types=1);

namespace Jelly\Tests\Unit\Middlewares;

use Jelly\Handlers\StaticResponse;
use Jelly\Middlewares\CrashFailSafe;
use Jelly\Tests\Fixtures\BrokenHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class CrashFailSafeTest extends TestCase
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
        $sut = new CrashFailSafe(new Response(500), new Psr17Factory(), true);

        $response = $sut->process(new ServerRequest('GET', '/hello'), new StaticResponse(new Response(204)));

        self::assertSame(204, $response->getStatusCode());
    }

    public function testExceptionInProdMode(): void
    {
        $sut = new CrashFailSafe(new Response(500), new Psr17Factory(), true);

        $response = $sut->process(new ServerRequest('GET', '/hello'), new BrokenHandler());

        self::assertSame(500, $response->getStatusCode());
        self::assertFalse($response->hasHeader('Content-Type'));
        self::assertSame('', (string) $response->getBody());
    }

    public function testExceptionInDevelopmentMode(): void
    {
        $sut = new CrashFailSafe(new Response(500), new Psr17Factory(), false);

        $response = $sut->process(new ServerRequest('GET', '/hello'), $handler = new BrokenHandler());

        self::assertSame(500, $response->getStatusCode());
        self::assertTrue($response->hasHeader('Content-Type'));
        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame((string) $handler->exception, (string) $response->getBody());
    }
}
