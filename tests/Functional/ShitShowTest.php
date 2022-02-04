<?php

declare(strict_types=1);

namespace ABC\Tests\Functional;

use ABC\Constants;
use ABC\Handlers\GenericResponse;
use ABC\Kernel;
use ABC\Tests\Fixtures\HelloHandler;
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
     * @dataProvider kernelBlowUpsProvider
     */
    public function testKernelBlowUps(ContainerInterface $container): void
    {
        $this->expectException(LogicException::class);

        new Kernel($container);
    }

    private function kernelBlowUpsProvider(): array
    {
        return [
            'Mandatory service NOT_FOUND_HANDLER missing in container' => [
                new Container([
                    Constants\Services::BAD_METHOD_HANDLER->value => new GenericResponse(new Response(405))
                ])
            ],

            'Mandatory service BAD_METHOD_HANDLER missing in container' => [
                new Container([
                    Constants\Services::NOT_FOUND_HANDLER->value => new GenericResponse(new Response(404))
                ])
            ],
        ];
    }

    /**
     * @dataProvider routeDefinitionExceptionsProvider
     */
    public function testRouteDefinitionExceptions(ContainerInterface $container): void
    {
        $this->expectException(LogicException::class);

        $kernel = new Kernel($container);
        $kernel->GET('/hello/{name}', HelloHandler::class);
    }

    private function routeDefinitionExceptionsProvider(): array
    {
        return [
            'The service name attached to a route definition is not present in the container' => [
                new Container([
                    Constants\Services::NOT_FOUND_HANDLER->value => new GenericResponse(new Response(404)),
                    Constants\Services::BAD_METHOD_HANDLER->value => new GenericResponse(new Response(405))
                ])
            ]
        ];
    }

    /**
     * @dataProvider runtimeErrorScenariosProvider
     */
    public function testRuntimeErrorScenarios(ServerRequest $request, ContainerInterface $container): void
    {
        $this->expectException(LogicException::class);

        $kernel = new Kernel($container);
        $kernel->GET('/hello/{name}', HelloHandler::class);
        $kernel->handle($request);
    }

    private function runtimeErrorScenariosProvider(): array
    {
        return [
            'The route that matches is not a RequestHandlerInterface' => [
                new ServerRequest('GET', '/hello/tron'),
                new Container([
                    HelloHandler::class => 'oh noes',
                    Constants\Services::NOT_FOUND_HANDLER->value => new GenericResponse(new Response(404)),
                    Constants\Services::BAD_METHOD_HANDLER->value => new GenericResponse(new Response(405))
                ])
            ],

            'Mandatory service NOT_FOUND_HANDLER is not a RequestHandlerInterface' => [
                new ServerRequest('GET', '/wrong/url'),
                new Container([
                    HelloHandler::class => new HelloHandler(),
                    Constants\Services::NOT_FOUND_HANDLER->value => 'aliki liki',
                    Constants\Services::BAD_METHOD_HANDLER->value => new GenericResponse(new Response(405))
                ])
            ],

            'Mandatory service BAD_METHOD_HANDLER is not a RequestHandlerInterface' => [
                new ServerRequest('POST', '/hello/tron'),
                new Container([
                    HelloHandler::class => new HelloHandler(),
                    Constants\Services::NOT_FOUND_HANDLER->value => new GenericResponse(new Response(404)),
                    Constants\Services::BAD_METHOD_HANDLER->value => 'quack quack quack'
                ])
            ],
        ];
    }

    // TODO middleware-related fuckups
}
