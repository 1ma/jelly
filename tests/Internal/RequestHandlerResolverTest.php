<?php

declare(strict_types=1);

namespace ABC\Tests\Internal;

use ABC\Constants;
use ABC\Internal\RequestHandlerResolver;
use ABC\Internal\RouteCollection;
use LogicException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use UMA\DIC\Container;
use function implode;
use function sprintf;

final class RequestHandlerResolverTest extends TestCase
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
                    $handler = $request->getAttribute(Constants\Attributes::HANDLER->value);
                    $args = $request->getAttribute(Constants\Attributes::ARGS->value);

                    $this->phpunit::assertSame('hello_handler', $handler);
                    $this->phpunit::assertSame(['name' => 'abc'], $args);

                    return new Response(200, [], sprintf('Hello, %s.', $args['name']));
                }
            };
        });

        $this->container->set('bogus_handler', static function(): RequestHandlerInterface {
            return new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new LogicException('This method MUST NOT be reached in order to pass the tests');
                }
            };
        });

        $this->container->set(Constants\Services::NOT_FOUND_HANDLER->value, static function(): RequestHandlerInterface {
            return new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(404, [], sprintf('Route %s not found', $request->getUri()->getPath()));
                }
            };
        });

        $this->container->set(Constants\Services::BAD_METHOD_HANDLER->value, function(): RequestHandlerInterface {
            return new class($this) implements RequestHandlerInterface {
                private readonly TestCase $phpunit;

                public function __construct(TestCase $phpunit)
                {
                    $this->phpunit = $phpunit;
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $allowedMethods = $request->getAttribute(Constants\Attributes::ALLOWED_METHODS->value);

                    $this->phpunit::assertIsArray($allowedMethods);
                    $this->phpunit::assertNotEmpty($allowedMethods);

                    return new Response(405, ['Allow' => implode(', ', $allowedMethods)]);
                }
            };
        });
    }

    public function testHappyPath(): void
    {
        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'hello_handler');

        self::assertFalse($this->container->resolved('hello_handler'));
        self::assertFalse($this->container->resolved(Constants\Services::NOT_FOUND_HANDLER->value));
        self::assertFalse($this->container->resolved(Constants\Services::BAD_METHOD_HANDLER->value));

        $request = new ServerRequest('GET', '/hello/abc');
        $response = $this->container->get((new RequestHandlerResolver($routes))->resolve($request))
            ->handle($request);

        self::assertTrue($this->container->resolved('hello_handler'));
        self::assertFalse($this->container->resolved(Constants\Services::NOT_FOUND_HANDLER->value));
        self::assertFalse($this->container->resolved(Constants\Services::BAD_METHOD_HANDLER->value));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Hello, abc.', (string) $response->getBody());
    }

    public function testNotFoundExecutionPath(): void
    {
        $routes = new RouteCollection;
        $routes->addRoute('GET', '/hello/{name}', 'bogus_handler');

        self::assertFalse($this->container->resolved('bogus_handler'));
        self::assertFalse($this->container->resolved(Constants\Services::NOT_FOUND_HANDLER->value));
        self::assertFalse($this->container->resolved(Constants\Services::BAD_METHOD_HANDLER->value));

        $request = new ServerRequest('GET', '/bye/abc');
        $response = $this->container->get((new RequestHandlerResolver($routes))->resolve($request))
            ->handle($request);

        self::assertFalse($this->container->resolved('bogus_handler'));
        self::assertTrue($this->container->resolved(Constants\Services::NOT_FOUND_HANDLER->value));
        self::assertFalse($this->container->resolved(Constants\Services::BAD_METHOD_HANDLER->value));

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
        self::assertFalse($this->container->resolved(Constants\Services::NOT_FOUND_HANDLER->value));
        self::assertFalse($this->container->resolved(Constants\Services::BAD_METHOD_HANDLER->value));

        $request = new ServerRequest('DELETE', '/hello/abc');
        $response = $this->container->get((new RequestHandlerResolver($routes))->resolve($request))
            ->handle($request);

        self::assertFalse($this->container->resolved('hello_handler'));
        self::assertFalse($this->container->resolved('bogus_handler'));
        self::assertFalse($this->container->resolved(Constants\Services::NOT_FOUND_HANDLER->value));
        self::assertTrue($this->container->resolved(Constants\Services::BAD_METHOD_HANDLER->value));

        self::assertSame(405, $response->getStatusCode());
        self::assertTrue($response->hasHeader('Allow'));
        self::assertSame('GET, POST', $response->getHeaderLine('Allow'));
    }
}