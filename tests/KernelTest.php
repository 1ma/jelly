<?php

declare(strict_types=1);

namespace ABC\Tests;

use ABC\Constants;
use ABC\Handler;
use ABC\Kernel;
use ABC\Middleware\SecurityHeaders;
use ABC\Middleware\ServerCloak;
use ABC\Tests\Fixture\SuccessfulHandler;
use ABC\Tests\Fixture\BrokenHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use UMA\DIC\Container;

final class KernelTest extends TestCase
{
    /**
     * @var Container
     */
    private $container;

    /**
     * @var Kernel
     */
    private $kernel;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();

        $this->container = new Container([
            Constants::NOT_FOUND_HANDLER => new Handler\EmptyResponse($factory, 404),
            Constants::BAD_METHOD_HANDLER => new Handler\MethodNotAllowed($factory),
            Constants::EXCEPTION_HANDLER => new Handler\DebugException($factory, $factory),
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

        $this->container->set(Constants::EXCEPTION_HANDLER, new Handler\EmptyResponse(new Psr17Factory, 503));

        self::assertExpectedResponse(
            $this->kernel->handle(new ServerRequest('GET', '/')),
            503,
            [],
            ''
        );
    }

    public function testKernelDecoration(): void
    {
        $this->kernel->GET('/', 'index');
        $this->kernel->decorate(new SecurityHeaders);

        self::assertExpectedResponse(
            $this->kernel->handle(new ServerRequest('GET', '/')),
            200,
            [
                'Content-Type' => ['text/plain'],
                'Expect-CT' => ['enforce,max-age=30'],
                'Strict-Transport-Security' => ['max-age=30'],
                'X-Content-Type-Options' => ['nosniff'],
                'X-Frame-Options' => ['DENY'],
                'X-XSS-Protection' => ['1; mode=block']
            ],
            'Hello.'
        );
    }

    /**
     * @runInSeparateProcess
     */
    public function testServerCloakMiddleware(): void
    {
        $this->kernel->GET('/', 'index');
        $this->kernel->decorate(new ServerCloak('api.example.com'));

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

    public function testTagsAndMiddlewares(): void
    {
        $this->kernel->GET('/', 'index', ['foo']);
        $this->container->set(SecurityHeaders::class, new SecurityHeaders);
        $this->kernel->add('foo', SecurityHeaders::class);

        self::assertExpectedResponse(
            $this->kernel->handle(new ServerRequest('GET', '/')),
            200,
            [
                'Content-Type' => ['text/plain'],
                'Expect-CT' => ['enforce,max-age=30'],
                'Strict-Transport-Security' => ['max-age=30'],
                'X-Content-Type-Options' => ['nosniff'],
                'X-Frame-Options' => ['DENY'],
                'X-XSS-Protection' => ['1; mode=block']
            ],
            'Hello.'
        );
    }

    /**
     * @throws ExpectationFailedException
     */
    private static function assertExpectedResponse(
        ResponseInterface $response,
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
