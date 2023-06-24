<?php

declare(strict_types=1);

namespace Jelly\Tests\Unit\Internal;

use Jelly\Internal\MiddlewareChainResolver;
use PHPUnit\Framework\TestCase;

final class MiddlewareChainResolverTest extends TestCase
{
    private MiddlewareChainResolver $sut;

    protected function setUp(): void
    {
        $this->sut = new MiddlewareChainResolver();
        $this->sut->pushHandler('index');
    }

    public function testEmptyResolver(): void
    {
        $sut = new MiddlewareChainResolver();

        self::assertSame([], $sut->resolve('index'));
    }

    public function testPushHandlerIsIdempotent(): void
    {
        $this->sut->pushLocalMiddleware('middleware', 'some-group');

        self::assertSame([], $this->sut->resolve('index'));

        $this->sut->pushHandler('index', 'some-group');

        self::assertSame(['middleware'], $this->sut->resolve('index'));
    }

    public function testDuplicateGroupsInPushHandlerAreIgnored(): void
    {
        $this->sut->pushHandler('dashboard', 'protected', 'protected', 'protected');
        $this->sut->pushLocalMiddleware('basic-auth', 'protected');

        self::assertSame(['basic-auth'], $this->sut->resolve('dashboard'));
    }

    public function testSimplestCaseWithNoMiddleware(): void
    {
        self::assertSame([], $this->sut->resolve('index'));
    }

    public function testGlobalMiddleware(): void
    {
        $this->sut->pushGlobalMiddleware('content-length');

        self::assertSame(['content-length'], $this->sut->resolve('index'));
    }

    public function testGlobalMiddlewareOrder(): void
    {
        $this->sut->pushGlobalMiddleware('content-length');
        $this->sut->pushGlobalMiddleware('security-headers');

        self::assertSame(['content-length', 'security-headers'], $this->sut->resolve('index'));
    }

    public function testLocalMiddleware(): void
    {
        $this->sut->pushLocalMiddleware('ip-logger', 'index');

        self::assertSame(['ip-logger'], $this->sut->resolve('index'));
    }

    public function testLocalMiddlewareOrder(): void
    {
        $this->sut->pushLocalMiddleware('basic-auth', 'index');
        $this->sut->pushLocalMiddleware('ip-logger', 'index');

        self::assertSame([
            'basic-auth',
            'ip-logger',
        ], $this->sut->resolve('index'));
    }

    public function testMixAndMatchGlobalAndLocalMiddlewares(): void
    {
        $this->sut->pushGlobalMiddleware('inner-global-middleware');
        $this->sut->pushLocalMiddleware('inner-local-middleware', 'index');
        $this->sut->pushGlobalMiddleware('outer-global-middleware');
        $this->sut->pushLocalMiddleware('outer-local-middleware', 'index');

        self::assertSame([
            'inner-local-middleware',
            'outer-local-middleware',
            'inner-global-middleware',
            'outer-global-middleware',
        ], $this->sut->resolve('index'));
    }

    public function testGroupTagging(): void
    {
        $this->sut->pushHandler('dashboard', 'monitored');
        $this->sut->pushLocalMiddleware('basic-auth', 'dashboard');
        $this->sut->pushLocalMiddleware('ip-logger', 'monitored');
        $this->sut->pushGlobalMiddleware('security-headers');

        self::assertSame(['security-headers'], $this->sut->resolve('index'));
        self::assertSame([
            'basic-auth',
            'ip-logger',
            'security-headers',
        ], $this->sut->resolve('dashboard'));
    }
}
