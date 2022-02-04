<?php

declare(strict_types=1);

namespace ABC\Tests\Unit\Middlewares;

use ABC\Middlewares\UncaughtExceptionDebugger;
use ABC\Tests\Fixtures\BrokenHandler;
use ABC\Tests\Fixtures\HelloHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class UncaughtExceptionDebuggerTest extends TestCase
{
    public function testSuccessfulRun(): void
    {
        $sut = new UncaughtExceptionDebugger(new Psr17Factory(), new Psr17Factory());

        $response = $sut->process(new ServerRequest('GET', '/hello'), new HelloHandler());

        self::assertSame(200, $response->getStatusCode());
    }

        public function testException(): void
    {
        $sut = new UncaughtExceptionDebugger(new Psr17Factory(), new Psr17Factory());

        $response = $sut->process(new ServerRequest('GET', '/hello'), new BrokenHandler());

        self::assertSame(500, $response->getStatusCode());
        self::assertTrue($response->hasHeader('Content-Type'));
        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertStringStartsWith('Exception Type:', (string) $response->getBody());
    }


}
