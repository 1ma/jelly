<?php

declare(strict_types=1);

namespace ABC\Tests;

use ABC\Constants;
use ABC\Handler;
use ABC\Kernel;
use ABC\Middleware\SecurityHeaders;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
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

    protected function setUp()
    {
        $factory = new Psr17Factory();

        $this->container = new Container([
            Constants::NOT_FOUND_HANDLER => new Handler\EmptyResponse($factory, 404),
            Constants::BAD_METHOD_HANDLER => new Handler\MethodNotAllowed($factory),
            Constants::EXCEPTION_HANDLER => new Handler\DebugException($factory, $factory),
            'index' => new class implements RequestHandlerInterface
            {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200, ['Content-Type' => 'text/plain'], 'Hello.');
                }
            },
            'boom' => new class implements RequestHandlerInterface
            {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new RuntimeException('Something went rekt.');
                }
            }
        ]);

        $this->kernel = new Kernel($this->container);
    }

    public function testHappyPath(): void
    {
        $this->kernel->get('/', 'index');

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
        $this->kernel->get('/', 'index');
        $this->kernel->post('/', 'index');
        $this->kernel->put('/', 'index');
        $this->kernel->update('/', 'index');
        $this->kernel->delete('/', 'index');
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
        $this->kernel->get('/', 'boom');

        self::assertExpectedResponse(
            $this->kernel->handle(new ServerRequest('GET', '/')),
            500,
            ['Content-Type' => ['text/plain']],
            'Something went rekt.', false
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
        $this->kernel->get('/', 'index');
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

    public function testTagsAndMiddlewares(): void
    {
        $this->kernel->get('/', 'index', ['foo']);
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
