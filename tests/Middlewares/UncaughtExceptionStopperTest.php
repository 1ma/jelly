<?php

declare(strict_types=1);

namespace ABC\Tests\Middlewares;

use ABC\Constants;
use ABC\Middlewares\UncaughtExceptionStopper;
use ABC\Tests\Fixtures\BrokenHandler;
use ABC\Tests\Fixtures\SuccessfulHandler;
use LogicException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use UMA\DIC\Container;

final class UncaughtExceptionStopperTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container;

        $this->container->set(self::class, $this);
        $this->container->set(Constants::EXCEPTION_HANDLER->value, function(Container $c): RequestHandlerInterface {
            return new class($c->get(self::class)) implements RequestHandlerInterface {
                private readonly TestCase $phpunit;

                public function __construct(TestCase $phpunit)
                {
                    $this->phpunit = $phpunit;
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $exception = $request->getAttribute(Constants::EXCEPTION->value);

                    $this->phpunit::assertInstanceOf(Throwable::class, $exception);
                    $this->phpunit::assertSame('Whoops!', $exception->getMessage());

                    return new Response(500, [], 'All is lost!');
                }
            };
        });
    }

    public function testHappyPath(): void
    {
        $response = (new UncaughtExceptionStopper($this->container))->process(
            new ServerRequest('GET', '/'),
            new SuccessfulHandler
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Hello.', (string) $response->getBody());
        self::assertFalse($this->container->resolved(Constants::EXCEPTION_HANDLER->value));
    }

    public function testExceptionPath(): void
    {
        $response = (new UncaughtExceptionStopper($this->container))->process(
            new ServerRequest('GET', '/'),
            new BrokenHandler
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('All is lost!', (string) $response->getBody());
        self::assertTrue($this->container->resolved(Constants::EXCEPTION_HANDLER->value));
    }

    public function testExceptionHandlerServiceIsNotDefined(): void
    {
        $this->expectException(LogicException::class);

        (new UncaughtExceptionStopper(new Container))->process(
            new ServerRequest('GET', '/'),
            new BrokenHandler()
        );
    }

    public function testExceptionHandlerServiceIsNotARequestHandler(): void
    {
        $this->expectException(LogicException::class);

        $this->container->set(Constants::EXCEPTION_HANDLER->value, 'WTF is this shit, I need a RequestHandlerInterface instance');

        (new UncaughtExceptionStopper($this->container))->process(
            new ServerRequest('GET', '/'),
            new BrokenHandler()
        );
    }
}
