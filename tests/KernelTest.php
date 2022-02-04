<?php

declare(strict_types=1);

namespace ABC\Tests;

use ABC\Constants;
use ABC\Handlers;
use ABC\Kernel;
use ABC\Middlewares\SecurityHeaders;
use ABC\Middlewares\ServerCloak;
use ABC\Tests\Fixtures\SuccessfulHandler;
use ABC\Tests\Fixtures\BrokenHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message;
use Psr\Http\Server;
use UMA\DIC\Container;

final class KernelTest extends TestCase
{
    private Container $container;
    private Kernel $kernel;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();

        $this->container = new Container([
            Constants::NOT_FOUND_HANDLER->value => new Handlers\EmptyResponse($factory, 404),
            Constants::BAD_METHOD_HANDLER->value => new Handlers\MethodNotAllowed($factory),
            Constants::EXCEPTION_HANDLER->value => new Handlers\DebugException($factory, $factory),
            SecurityHeaders::class => new SecurityHeaders(),
            ServerCloak::class => new ServerCloak('api.example.com'),
            'index' => new SuccessfulHandler,
            'boom' => new BrokenHandler
        ]);

        $this->kernel = new Kernel($this->container);
    }

    public function testHappyPath(): void
    {
        $this->kernel->GET('/', 'index');

        self::assertExpectedResponse(
            $this->kernel->handle(new ServerRequest('GET', '/')),
            200,
            ['Content-Type' => ['text/plain']],
            'Hello.'
        );
    }

    public function testNotFound(): void
    {
        self::assertExpectedResponse(
            $this->kernel->handle(new ServerRequest('GET', '/')),
            404,
            [],
            ''
        );
    }

    public function testBadMethod(): void
    {
        $this->kernel->GET('/', 'index');
        $this->kernel->POST('/', 'index');
        $this->kernel->PUT('/', 'index');
        $this->kernel->UPDATE('/', 'index');
        $this->kernel->DELETE('/', 'index');
        $this->kernel->map('OPTIONS', '/', 'index');

        self::assertExpectedResponse(
            $this->kernel->handle(new ServerRequest('PATCH', '/')),
            405,
            ['Allow' => ['GET, POST, PUT, UPDATE, DELETE, OPTIONS']],
            ''
        );
    }

    public function testExceptionHandler(): void
    {
        $this->kernel->GET('/', 'boom');

        self::assertExpectedResponse(
            $this->kernel->handle(new ServerRequest('GET', '/')),
            500,
            ['Content-Type' => ['text/plain']],
            'Whoops!', false
        );

        $this->container->set(Constants::EXCEPTION_HANDLER->value, new Handlers\EmptyResponse(new Psr17Factory, 503));

        self::assertExpectedResponse(
            $this->kernel->handle(new ServerRequest('GET', '/')),
            503,
            [],
            ''
        );
    }

    public function testKernelWrapping(): void
    {
        $this->kernel->GET('/', 'index');
        $this->kernel->wrap(SecurityHeaders::class);

        self::assertExpectedResponse(
            $this->kernel->handle(new ServerRequest('GET', '/')),
            200,
            [
                'Content-Type' => ['text/plain'],
                'Expect-CT' => ['enforce,max-age=30'],
                'Permissions-Policy' => ['interest-cohort=()'],
                'Strict-Transport-Security' => ['max-age=30'],
                'X-Content-Type-Options' => ['nosniff'],
                'X-Frame-Options' => ['DENY'],
                'X-XSS-Protection' => ['1; mode=block']
            ],
            'Hello.'
        );
    }

    public function testResolvedHandlerAndArgsAreAvailableToMiddlewares(): void
    {
        $this->container->set('adhoc_middleware', new class($this) implements Server\MiddlewareInterface {
            private readonly TestCase $phpunit;

            public function __construct(TestCase $phpunit)
            {
                $this->phpunit = $phpunit;
            }

            public function process(Message\ServerRequestInterface $request, Server\RequestHandlerInterface $handler): Message\ResponseInterface
            {
                $this->phpunit::assertSame('index', $request->getAttribute(Constants::HANDLER->value));
                $this->phpunit::assertSame(['name' => 'joe'], $request->getAttribute(Constants::ARGS->value));

                return $handler->handle($request);
            }
        });

        $this->kernel->GET('/hello/{name}', 'index');
        $this->kernel->wrap('adhoc_middleware');

        self::assertExpectedResponse(
            $this->kernel->handle(new ServerRequest('GET', '/hello/joe')),
            200,
            [
                'Content-Type' => ['text/plain']
            ],
            'Hello joe.'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testServerCloakMiddleware(): void
    {
        $this->kernel->GET('/', 'index');
        $this->kernel->wrap(ServerCloak::class);

        self::assertExpectedResponse(
            $this->kernel->handle(new ServerRequest('GET', '/')),
            200,
            [
                'Content-Type' => ['text/plain'],
                'Server' => ['api.example.com']
            ],
            'Hello.'
        );
    }

    /**
     * @throws ExpectationFailedException
     */
    private static function assertExpectedResponse(
        Message\ResponseInterface $response,
        int $expectedStatusCode,
        array $expectedHeaders,
        string $expectedBody,
        bool $exactMatch = true
    ): void
    {
        self::assertSame($expectedStatusCode, $response->getStatusCode());
        self::assertSame($expectedHeaders, $response->getHeaders());

        $exactMatch ?
            self::assertSame($expectedBody, (string)$response->getBody()) :
            self::assertStringContainsString($expectedBody, (string)$response->getBody());
    }
}
