<?php

declare(strict_types=1);

namespace ABC\Tests\Internal;

use ABC\Internal\RequestRouter;
use ABC\Internal\RouteCollection;
use ABC\Kernel;
use LogicException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use UMA\DIC\Container;

final class RequestRouterTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container;

        $this->container->set('hello_handler', function(): RequestHandlerInterface {
            return new class($this) implements RequestHandlerInterface {
                private readonly TestCase $phpunit;

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

        $this->container->set(Kernel::NOT_FOUND_HANDLER_SERVICE, function(): RequestHandlerInterface {
            return new class($this) implements RequestHandlerInterface {
                private readonly TestCase $phpunit;

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

        $this->container->set(Kernel::BAD_METHOD_HANDLER_SERVICE, function(): RequestHandlerInterface {
            return new class($this) implements RequestHandlerInterface {
                private readonly TestCase $phpunit;

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

        $request = new ServerRequest('GET', '/hello/abc');
        $response = (new RequestRouter($this->container, $routes))
            ->resolve($request)
            ->handle($request);

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

        $request = new ServerRequest('GET', '/bye/abc');
        $response = (new RequestRouter($this->container, $routes))
            ->resolve($request)
            ->handle($request);

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

        $request = new ServerRequest('DELETE', '/hello/abc');
        $response = (new RequestRouter($this->container, $routes))
            ->resolve($request)
            ->handle($request);

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
        $this->expectException(RuntimeException::class);

        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');

        $request = new ServerRequest('GET', '/bye/abc');
        (new RequestRouter(new Container, $routes))
            ->resolve($request)
            ->handle($request);
    }

    public function testBadMethodHandlerServiceNotDefined(): void
    {
        $this->expectException(RuntimeException::class);

        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');
        $routes->addRoute('POST', '/hello/{name}', 'hello_handler');

        $request = new ServerRequest('DELETE', '/hello/abc');
        (new RequestRouter(new Container, $routes))
            ->resolve($request)
            ->handle($request);
    }

    public function testNotFoundHandlerServiceIsNotARequestHandler(): void
    {
        $this->expectException(RuntimeException::class);

        $this->container->set(Kernel::NOT_FOUND_HANDLER_SERVICE, 123);

        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');

        $request = new ServerRequest('GET', '/bye/abc');
        (new RequestRouter($this->container, $routes))
            ->resolve($request)
            ->handle($request);
    }

    public function testBadMethodHandlerServiceIsNotARequestHandler(): void
    {
        $this->expectException(RuntimeException::class);

        $this->container->set(Kernel::BAD_METHOD_HANDLER_SERVICE, 123);

        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');

        $request = new ServerRequest('DELETE', '/hello/abc');
        (new RequestRouter($this->container, $routes))
            ->resolve($request)
            ->handle($request);
    }
}
