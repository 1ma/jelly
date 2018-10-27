<?php

declare(strict_types=1);

namespace ABC\Tests;

use ABC\Constants;
use ABC\Middleware\RequestRouter;
use ABC\Util\RouteCollection;
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
    /**
     * @var Container
     */
    private $container;

    protected function setUp()
    {
        $this->container = new Container;

        $this->container->set(self::class, $this);
        $this->container->set(Constants::NOT_FOUND_HANDLER, function(Container $c): RequestHandlerInterface {
            return new class($c->get(self::class)) implements RequestHandlerInterface {
                private $phpunit;

                public function __construct(TestCase $phpunit)
                {
                    $this->phpunit = $phpunit;
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $errorType = $request->getAttribute(Constants::ERROR_TYPE);

                    $this->phpunit::assertSame(404, $errorType);

                    return new Response($errorType, [], \sprintf('Route %s not found', $request->getUri()->getPath()));
                }
            };
        });
        $this->container->set(Constants::BAD_METHOD_HANDLER, function(Container $c): RequestHandlerInterface {
            return new class($c->get(self::class)) implements RequestHandlerInterface {
                private $phpunit;

                public function __construct(TestCase $phpunit)
                {
                    $this->phpunit = $phpunit;
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $errorType = $request->getAttribute(Constants::ERROR_TYPE);
                    $allowedMethods = $request->getAttribute(Constants::ALLOWED_METHODS);

                    $this->phpunit::assertSame(405, $errorType);
                    $this->phpunit::assertInternalType('array', $allowedMethods);
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

        $response = (new RequestRouter($this->container, $routes))->process(
            new ServerRequest('GET', '/hello/Human'),
            new class($this) implements RequestHandlerInterface {
                private $phpunit;

                public function __construct(TestCase $phpunit)
                {
                    $this->phpunit = $phpunit;
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $handler = $request->getAttribute(Constants::HANDLER);
                    $args = $request->getAttribute(Constants::ARGS);

                    $this->phpunit::assertSame('hello_handler', $handler);
                    $this->phpunit::assertSame(['name' => 'Human'], $args);

                    return new Response(200, [], \sprintf('Hello, %s.', $args['name']));
                }
            }
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Hello, Human.', (string) $response->getBody());
        self::assertFalse($this->container->resolved(Constants::NOT_FOUND_HANDLER));
        self::assertFalse($this->container->resolved(Constants::BAD_METHOD_HANDLER));
    }

    public function testNotFoundExecutionPath(): void
    {
        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');

        $response = (new RequestRouter($this->container, $routes))->process(
            new ServerRequest('GET', '/bye/Human'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new LogicException('This handler must not be reached');
                }
            }
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Route /bye/Human not found', (string) $response->getBody());
        self::assertTrue($this->container->resolved(Constants::NOT_FOUND_HANDLER));
        self::assertFalse($this->container->resolved(Constants::BAD_METHOD_HANDLER));
    }

    public function testBadMethodExecutionPath(): void
    {
        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');

        $response = (new RequestRouter($this->container, $routes))->process(
            new ServerRequest('DELETE', '/hello/Human'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new LogicException('This handler must not be reached');
                }
            }
        );

        self::assertSame(405, $response->getStatusCode());
        self::assertTrue($response->hasHeader('Allow'));
        self::assertSame('GET', $response->getHeaderLine('Allow'));
        self::assertFalse($this->container->resolved(Constants::NOT_FOUND_HANDLER));
        self::assertTrue($this->container->resolved(Constants::BAD_METHOD_HANDLER));
    }

    public function testNotFoundHandlerServiceNotDefined(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);

        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');

        (new RequestRouter(new Container, $routes))->process(
            new ServerRequest('GET', '/bye/Human'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new LogicException('This handler must not be reached');
                }
            }
        );
    }

    public function testBadMethodHandlerServiceNotDefined(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);

        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');

        (new RequestRouter(new Container, $routes))->process(
            new ServerRequest('DELETE', '/hello/Human'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new LogicException('This handler must not be reached');
                }
            }
        );
    }

    public function testNotFoundHandlerServiceIsNotARequestHandler(): void
    {
        $this->expectException(TypeError::class);

        $this->container->set(Constants::NOT_FOUND_HANDLER, 123);

        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');

        (new RequestRouter($this->container, $routes))->process(
            new ServerRequest('GET', '/bye/Human'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new LogicException('This handler must not be reached');
                }
            }
        );
    }

    public function testBadMethodHandlerServiceIsNotARequestHandler(): void
    {
        $this->expectException(TypeError::class);

        $this->container->set(Constants::BAD_METHOD_HANDLER, 123);

        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');

        (new RequestRouter($this->container, $routes))->process(
            new ServerRequest('DELETE', '/hello/Human'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new LogicException('This handler must not be reached');
                }
            }
        );
    }
}
