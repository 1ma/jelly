<?php

declare(strict_types=1);

namespace ABC\Tests\Handlers;

use ABC\Handlers\RequestRouter;
use ABC\Internal\RouteCollection;
use ABC\Kernel;
use LogicException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TypeError;
use UMA\DIC\Container;

final class RequestRouterTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container;

        $this->container->set(self::class, $this);

        $this->container->set('hello_handler', function(Container $c): RequestHandlerInterface {
            return new class($c->get(self::class)) implements RequestHandlerInterface {
                private $phpunit;

                public function __construct(TestCase $phpunit)
                {
                    $this->phpunit = $phpunit;
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $handler = $request->getAttribute(Kernel::HANDLER);
                    $args = $request->getAttribute(Kernel::ARGS);

                    $this->phpunit::assertSame('hello_handler', $handler);
                    $this->phpunit::assertSame(['name' => 'abc'], $args);

                    return new Response(200, [], \sprintf('Hello, %s.', $args['name']));
                }
            };
        });

        $this->container->set('bogus_handler', function(): RequestHandlerInterface {
            return new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new LogicException('This method MUST NOT be reached in order to pass the tests');
                }
            };
        });

        $this->container->set(Kernel::NOT_FOUND_HANDLER_SERVICE, function(Container $c): RequestHandlerInterface {
            return new class($c->get(self::class)) implements RequestHandlerInterface {
                private $phpunit;

                public function __construct(TestCase $phpunit)
                {
                    $this->phpunit = $phpunit;
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $errorType = $request->getAttribute(Kernel::ERROR_TYPE);

                    $this->phpunit::assertSame(404, $errorType);

                    return new Response($errorType, [], \sprintf('Route %s not found', $request->getUri()->getPath()));
                }
            };
        });

        $this->container->set(Kernel::BAD_METHOD_HANDLER_SERVICE, function(Container $c): RequestHandlerInterface {
            return new class($c->get(self::class)) implements RequestHandlerInterface {
                private $phpunit;

                public function __construct(TestCase $phpunit)
                {
                    $this->phpunit = $phpunit;
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $errorType = $request->getAttribute(Kernel::ERROR_TYPE);
                    $allowedMethods = $request->getAttribute(Kernel::ALLOWED_METHODS);

                    $this->phpunit::assertSame(405, $errorType);
                    $this->phpunit::assertIsArray($allowedMethods);
                    $this->phpunit::assertNotEmpty($allowedMethods);

                    return new Response($errorType, ['Allow' => \implode(', ', $allowedMethods)]);
                }
            };
        });
    }

    public function testHappyPath(): void
    {
        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');

        self::assertFalse($this->container->resolved('hello_handler'));
        self::assertFalse($this->container->resolved(Kernel::NOT_FOUND_HANDLER_SERVICE));
        self::assertFalse($this->container->resolved(Kernel::BAD_METHOD_HANDLER_SERVICE));

        $response = (new RequestRouter($this->container, $routes))
            ->handle(new ServerRequest('GET', '/hello/abc'));

        self::assertTrue($this->container->resolved('hello_handler'));
        self::assertFalse($this->container->resolved(Kernel::NOT_FOUND_HANDLER_SERVICE));
        self::assertFalse($this->container->resolved(Kernel::BAD_METHOD_HANDLER_SERVICE));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Hello, abc.', (string) $response->getBody());
    }

    public function testNotFoundExecutionPath(): void
    {
        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'bogus_handler');

        self::assertFalse($this->container->resolved('bogus_handler'));
        self::assertFalse($this->container->resolved(Kernel::NOT_FOUND_HANDLER_SERVICE));
        self::assertFalse($this->container->resolved(Kernel::BAD_METHOD_HANDLER_SERVICE));

        $response = (new RequestRouter($this->container, $routes))
            ->handle(new ServerRequest('GET', '/bye/abc'));

        self::assertFalse($this->container->resolved('bogus_handler'));
        self::assertTrue($this->container->resolved(Kernel::NOT_FOUND_HANDLER_SERVICE));
        self::assertFalse($this->container->resolved(Kernel::BAD_METHOD_HANDLER_SERVICE));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Route /bye/abc not found', (string) $response->getBody());
    }

    public function testBadMethodExecutionPath(): void
    {
        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');
        $routes->addRoute('POST', '/hello/{name}', 'bogus_handler');

        self::assertFalse($this->container->resolved('hello_handler'));
        self::assertFalse($this->container->resolved('bogus_handler'));
        self::assertFalse($this->container->resolved(Kernel::NOT_FOUND_HANDLER_SERVICE));
        self::assertFalse($this->container->resolved(Kernel::BAD_METHOD_HANDLER_SERVICE));

        $response = (new RequestRouter($this->container, $routes))
            ->handle(new ServerRequest('DELETE', '/hello/abc'));

        self::assertFalse($this->container->resolved('hello_handler'));
        self::assertFalse($this->container->resolved('bogus_handler'));
        self::assertFalse($this->container->resolved(Kernel::NOT_FOUND_HANDLER_SERVICE));
        self::assertTrue($this->container->resolved(Kernel::BAD_METHOD_HANDLER_SERVICE));

        self::assertSame(405, $response->getStatusCode());
        self::assertTrue($response->hasHeader('Allow'));
        self::assertSame('GET, POST', $response->getHeaderLine('Allow'));
    }

    public function testNotFoundHandlerServiceNotDefined(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);

        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');

        (new RequestRouter(new Container, $routes))
            ->handle(new ServerRequest('GET', '/bye/abc'));
    }

    public function testBadMethodHandlerServiceNotDefined(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);

        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');
        $routes->addRoute('POST', '/hello/{name}', 'hello_handler');

        (new RequestRouter(new Container, $routes))
            ->handle(new ServerRequest('DELETE', '/hello/abc'));
    }

    public function testNotFoundHandlerServiceIsNotARequestHandler(): void
    {
        $this->expectException(TypeError::class);

        $this->container->set(Kernel::NOT_FOUND_HANDLER_SERVICE, 123);

        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');

        (new RequestRouter($this->container, $routes))
            ->handle(new ServerRequest('GET', '/bye/abc'));
    }

    public function testBadMethodHandlerServiceIsNotARequestHandler(): void
    {
        $this->expectException(TypeError::class);

        $this->container->set(Kernel::BAD_METHOD_HANDLER_SERVICE, 123);

        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');

        (new RequestRouter($this->container, $routes))
            ->handle(new ServerRequest('DELETE', '/hello/abc'));
    }
}
