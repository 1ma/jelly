<?php

declare(strict_types=1);

namespace ABC\Tests\Middlewares;

use ABC\Kernel;
use ABC\Middlewares\ExceptionTrapper;
use ABC\Tests\Fixtures\BrokenHandler;
use ABC\Tests\Fixtures\SuccessfulHandler;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use TypeError;
use UMA\DIC\Container;

final class ExceptionTrapperTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container;

        $this->container->set(self::class, $this);
        $this->container->set(Kernel::EXCEPTION_HANDLER_SERVICE, function(Container $c): RequestHandlerInterface {
            return new class($c->get(self::class)) implements RequestHandlerInterface {
                private $phpunit;

                public function __construct(TestCase $phpunit)
                {
                    $this->phpunit = $phpunit;
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $errorType = $request->getAttribute(Kernel::ERROR_TYPE);
                    $exception = $request->getAttribute(Kernel::EXCEPTION);

                    $this->phpunit::assertSame(500, $errorType);
                    $this->phpunit::assertInstanceOf(Throwable::class, $exception);
                    $this->phpunit::assertSame('Whoops!', $exception->getMessage());

                    return new Response($errorType, [], 'All is lost!');
                }
            };
        });
    }

    public function testHappyPath(): void
    {
        $response = (new ExceptionTrapper($this->container))->process(
            new ServerRequest('GET', '/'),
            new SuccessfulHandler
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Hello.', (string) $response->getBody());
        self::assertFalse($this->container->resolved(Kernel::EXCEPTION_HANDLER_SERVICE));
    }

    public function testExceptionPath(): void
    {
        $response = (new ExceptionTrapper($this->container))->process(
            new ServerRequest('GET', '/'),
            new BrokenHandler
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('All is lost!', (string) $response->getBody());
        self::assertTrue($this->container->resolved(Kernel::EXCEPTION_HANDLER_SERVICE));
    }

    public function testExceptionHandlerServiceIsNotDefined(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);

        (new ExceptionTrapper(new Container))->process(
            new ServerRequest('GET', '/'),
            new BrokenHandler
        );
    }

    public function testExceptionHandlerServiceIsNotARequestHandler(): void
    {
        $this->expectException(TypeError::class);

        $this->container->set(Kernel::EXCEPTION_HANDLER_SERVICE, 'WTF is this shit, I need a RequestHandlerInterface instance');

        (new ExceptionTrapper($this->container))->process(
            new ServerRequest('GET', '/'),
            new BrokenHandler
        );
    }
}
