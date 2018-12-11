<?php

declare(strict_types=1);

namespace ABC\Tests;

use ABC\Constants;
use ABC\Handler;
use ABC\Kernel;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
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
            Constants::EXCEPTION_HANDLER => new Handler\DebugException($factory, $factory)
        ]);

        $this->kernel = new Kernel($this->container);
    }

    public function testHappyPath(): void
    {
        $this->container->set('index', new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, ['Content-Type' => 'text/plain'], 'Hello.');
            }
        });

        $this->kernel->get('/', 'index');

        $response = $this->kernel->handle(new ServerRequest('GET', '/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['Content-Type' => ['text/plain']], $response->getHeaders());
        self::assertSame('Hello.', (string) $response->getBody());
    }
}
