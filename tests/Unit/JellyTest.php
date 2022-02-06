<?php

declare(strict_types=1);

namespace Jelly\Tests\Unit;

use Jelly\Constants;
use Jelly\Handlers;
use Jelly\Jelly;
use Jelly\Middlewares\SecurityHeaders;
use Jelly\Middlewares\ServerCloak;
use Jelly\Tests\Fixtures\BrokenHandler;
use Jelly\Tests\Fixtures\TripwireMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message;
use Psr\Http\Server;
use UMA\DIC\Container;

final class JellyTest extends TestCase
{
    private Container $container;
    private Jelly $jelly;
    private TripwireMiddleware $tripwire;

    protected function setUp(): void
    {
        $this->tripwire = new TripwireMiddleware();
        $this->container = new Container([
            Constants\Services::NOT_FOUND_HANDLER->value => new Handlers\StaticResponse(new Response(404)),
            Constants\Services::BAD_METHOD_HANDLER->value => new Handlers\StaticResponse(new Response(405, ['Allow' => 'GET, POST, PUT, UPDATE, DELETE, OPTIONS'])),
            SecurityHeaders::class => new SecurityHeaders(),
            ServerCloak::class => new ServerCloak('api.example.com'),
            TripwireMiddleware::class => $this->tripwire,
            'index' => new Handlers\StaticResponse(new Response(200, headers: ['Content-Type' => 'text/plain'], body: 'Hello.')),
            'boom' => new BrokenHandler
        ]);

        $this->jelly = new Jelly($this->container);
    }

    public function testHappyPath(): void
    {
        $this->jelly->GET('/', 'index');

        self::assertExpectedResponse(
            $this->jelly->handle(new ServerRequest('GET', '/')),
            200,
            ['Content-Type' => ['text/plain']],
            'Hello.'
        );
    }

    public function testNotFound(): void
    {
        self::assertExpectedResponse(
            $this->jelly->handle(new ServerRequest('GET', '/')),
            404,
            [],
            ''
        );
    }

    public function testBadMethod(): void
    {
        $this->jelly->GET('/', 'index');
        $this->jelly->POST('/', 'index');
        $this->jelly->PUT('/', 'index');
        $this->jelly->UPDATE('/', 'index');
        $this->jelly->DELETE('/', 'index');
        $this->jelly->map('OPTIONS', '/', 'index');

        self::assertExpectedResponse(
            $this->jelly->handle(new ServerRequest('PATCH', '/')),
            405,
            ['Allow' => ['GET, POST, PUT, UPDATE, DELETE, OPTIONS']],
            ''
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testWrapping(): void
    {
        $this->jelly->GET('/', 'index');
        $this->jelly->wrap(ServerCloak::class);
        $this->jelly->wrap(SecurityHeaders::class);

        self::assertExpectedResponse(
            $this->jelly->handle(new ServerRequest('GET', '/')),
            200,
            [
                'Content-Type' => ['text/plain'],
                'Server' => ['api.example.com'],
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

    public function testTagging(): void
    {
        $this->jelly->GET('/', 'index');
        $this->jelly->tag(TripwireMiddleware::class, 'index');

        self::assertExpectedResponse(
            $this->jelly->handle(new ServerRequest('GET', '/404')),
            404,
            [],
            ''
        );

        self::assertFalse($this->tripwire->tripped);

        self::assertExpectedResponse(
            $this->jelly->handle(new ServerRequest('GET', '/')),
            200,
            ['Content-Type' => ['text/plain']],
            'Hello.'
        );

        self::assertTrue($this->tripwire->tripped);
    }

    public function testAttributesAreSet(): void
    {
        $this->container->set('adhoc_middleware', new class($this) implements Server\MiddlewareInterface {
            private readonly TestCase $phpunit;

            public function __construct(TestCase $phpunit)
            {
                $this->phpunit = $phpunit;
            }

            public function process(Message\ServerRequestInterface $request, Server\RequestHandlerInterface $handler): Message\ResponseInterface
            {
                $this->phpunit::assertSame('index', $request->getAttribute(Constants\Attributes::HANDLER->value));
                $this->phpunit::assertSame(['name' => 'joe'], $request->getAttribute(Constants\Attributes::ARGS->value));
                $this->phpunit::assertSame(['adhoc_middleware'], $request->getAttribute(Constants\Attributes::MIDDLEWARE_CHAIN->value));

                return $handler->handle($request);
            }
        });

        $this->jelly->GET('/hello/{name}', 'index');
        $this->jelly->wrap('adhoc_middleware');

        self::assertExpectedResponse(
            $this->jelly->handle(new ServerRequest('GET', '/hello/joe')),
            200,
            [
                'Content-Type' => ['text/plain']
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
