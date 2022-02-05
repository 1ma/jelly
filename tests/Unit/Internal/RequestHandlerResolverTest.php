<?php

declare(strict_types=1);

namespace Jelly\Tests\Unit\Internal;

use Jelly\Constants;
use Jelly\Internal\RequestHandlerResolver;
use Jelly\Internal\RouteCollection;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class RequestHandlerResolverTest extends TestCase
{
    public function testHappyPath(): void
    {
        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');

        $request = new ServerRequest('GET', '/hello/jelly');
        $name = (new RequestHandlerResolver($routes))->resolve($request);

        self::assertSame('hello_handler', $name);
        self::assertSame('hello_handler', $request->getAttribute(Constants\Attributes::HANDLER->value));
        self::assertSame(['name' => 'jelly'], $request->getAttribute(Constants\Attributes::ARGS->value));
    }

    public function testNotFoundExecutionPath(): void
    {
        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'bogus_handler');

        $request = new ServerRequest('GET', '/bye/jelly');
        $name = (new RequestHandlerResolver($routes))->resolve($request);

        self::assertSame(Constants\Services::NOT_FOUND_HANDLER->value, $name);
        self::assertNull($request->getAttribute(Constants\Attributes::HANDLER->value));
        self::assertNull($request->getAttribute(Constants\Attributes::ARGS->value));
    }

    public function testBadMethodExecutionPath(): void
    {
        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');
        $routes->addRoute('POST', '/hello/{name}', 'bogus_handler');

        $request = new ServerRequest('DELETE', '/hello/jelly');
        $name = (new RequestHandlerResolver($routes))->resolve($request);

        self::assertSame(Constants\Services::BAD_METHOD_HANDLER->value, $name);
        self::assertNull($request->getAttribute(Constants\Attributes::HANDLER->value));
        self::assertNull($request->getAttribute(Constants\Attributes::ARGS->value));
    }
}
