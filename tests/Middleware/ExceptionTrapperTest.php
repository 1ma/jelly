<?php

declare(strict_types=1);

namespace ABC\Tests\Middleware;

use ABC\Constants;
use ABC\Middleware\ExceptionTrapper;
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
    /**
     * @var Container
     */
    private $container;

    protected function setUp()
    {
        $this->container = new Container;

        $this->container->set(self::class, $this);
        $this->container->set(Constants::EXCEPTION_HANDLER, function(Container $c): RequestHandlerInterface {
            return new class($c->get(self::class)) implements RequestHandlerInterface {
                private $phpunit;

                public function __construct(TestCase $phpunit)
                {
                    $this->phpunit = $phpunit;
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $errorType = $request->getAttribute(Constants::ERROR_TYPE);
                    $exception = $request->getAttribute(Constants::EXCEPTION);

                    $this->phpunit::assertSame(500, $errorType);
                    $this->phpunit::assertInstanceOf(Throwable::class, $exception);
                    $this->phpunit::assertSame('Whoops', $exception->getMessage());

                    return new Response($errorType, [], 'All is lost!');
                }
            };
        });
    }

    public function testHappyPath(): void
    {
        $response = (new ExceptionTrapper($this->container))->process(
            new ServerRequest('GET', '/'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200, [], 'Everything is fine');
                }
            }
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Everything is fine', (string) $response->getBody());
        self::assertFalse($this->container->resolved(Constants::EXCEPTION_HANDLER));
    }

    public function testExceptionPath(): void
    {
        $response = (new ExceptionTrapper($this->container))->process(
            new ServerRequest('GET', '/'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new \RuntimeException('Whoops');
                }
            }
        );

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('All is lost!', (string) $response->getBody());
        self::assertTrue($this->container->resolved(Constants::EXCEPTION_HANDLER));
    }

    public function testExceptionHandlerServiceIsNotDefined(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);

        (new ExceptionTrapper(new Container))->process(
            new ServerRequest('GET', '/'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new \RuntimeException('Whoops');
                }
            }
        );
    }

    public function testExceptionHandlerServiceIsNotARequestHandler(): void
    {
        $this->expectException(TypeError::class);

        $this->container->set(Constants::EXCEPTION_HANDLER, 'WTF is this shit, I need a RequestHandlerInterface instance');

        (new ExceptionTrapper($this->container))->process(
            new ServerRequest('GET', '/'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    throw new \RuntimeException('Whoops');
                }
            }
        );
    }
}
