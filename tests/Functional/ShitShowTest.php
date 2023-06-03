<?php

declare(strict_types=1);

namespace Jelly\Tests\Functional;

use Jelly\Constants;
use Jelly\Handlers\StaticResponse;
use Jelly\Jelly;
use LogicException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use UMA\DIC\Container;

/**
 * Tests all the wrong ways you can set up the framework
 * so that it throws a LogicException when it runs.
 */
final class ShitShowTest extends TestCase
{
    /**
     * Tests framework exceptions when constructing the Jelly object
     *
     * @dataProvider constructorBlowUpsProvider
     */
    public function testConstructorBlowUps(ContainerInterface $container): void
    {
        $this->expectException(LogicException::class);

        new Jelly($container);
    }

    public static function constructorBlowUpsProvider(): array
    {
        return [
            'Mandatory service NOT_FOUND_HANDLER missing in container' => [
                new Container([
                    Constants\Services::BAD_METHOD_HANDLER->value => new StaticResponse(new Response(405))
                ])
            ],

            'Mandatory service BAD_METHOD_HANDLER missing in container' => [
                new Container([
                    Constants\Services::NOT_FOUND_HANDLER->value => new StaticResponse(new Response(404))
                ])
            ],
        ];
    }

    /**
     * Tests framework exceptions when adding route definitions
     *
     * @dataProvider routeDefinitionExceptionsProvider
     */
    public function testRouteDefinitionExceptions(ContainerInterface $container): void
    {
        $this->expectException(LogicException::class);

        $jelly = new Jelly($container);
        $jelly->GET('/hello/{name}', StaticResponse::class);
    }

    public static function routeDefinitionExceptionsProvider(): array
    {
        return [
            'The service name attached to a route definition is not present in the container' => [
                new Container([
                    Constants\Services::NOT_FOUND_HANDLER->value => new StaticResponse(new Response(404)),
                    Constants\Services::BAD_METHOD_HANDLER->value => new StaticResponse(new Response(405))
                ])
            ]
        ];
    }

    // TODO Test framework exceptions when adding global middlewares
    // TODO Test framework exceptions when adding group middlewares

    /**
     * Tests framework exceptions while handling an actual request
     *
     * @dataProvider runtimeErrorScenariosProvider
     */
    public function testRuntimeErrorScenarios(ServerRequest $request, ContainerInterface $container): void
    {
        $this->expectException(LogicException::class);

        $jelly = new Jelly($container);
        $jelly->GET('/hello/{name}', StaticResponse::class);
        $jelly->handle($request);
    }

    public static function runtimeErrorScenariosProvider(): array
    {
        return [
            'Turns out the route that matched the request is not a RequestHandlerInterface' => [
                new ServerRequest('GET', '/hello/jelly'),
                new Container([
                    StaticResponse::class => 'oh noes',
                    Constants\Services::NOT_FOUND_HANDLER->value => new StaticResponse(new Response(404)),
                    Constants\Services::BAD_METHOD_HANDLER->value => new StaticResponse(new Response(405))
                ])
            ],

            'Turns out the NOT_FOUND_HANDLER is not actually a RequestHandlerInterface' => [
                new ServerRequest('GET', '/wrong/url'),
                new Container([
                    StaticResponse::class => new StaticResponse(new Response(200)),
                    Constants\Services::NOT_FOUND_HANDLER->value => 'aliki liki',
                    Constants\Services::BAD_METHOD_HANDLER->value => new StaticResponse(new Response(405))
                ])
            ],

            'Turns out the BAD_METHOD_HANDLER is not actually a RequestHandlerInterface' => [
                new ServerRequest('POST', '/hello/jelly'),
                new Container([
                    StaticResponse::class => new StaticResponse(new Response(200)),
                    Constants\Services::NOT_FOUND_HANDLER->value => new StaticResponse(new Response(404)),
                    Constants\Services::BAD_METHOD_HANDLER->value => 'quack quack quack'
                ])
            ],
        ];
    }
}
