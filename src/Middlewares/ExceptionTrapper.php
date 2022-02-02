<?php

declare(strict_types=1);

namespace ABC\Middlewares;

use ABC\Internal\Assert;
use ABC\Kernel;
use Psr\Container;
use Psr\Http\Message;
use Psr\Http\Server;
use Throwable;
use TypeError;

final class ExceptionTrapper implements Server\MiddlewareInterface
{
    private readonly Container\ContainerInterface $container;

    public function __construct(Container\ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     * @throws TypeError
     */
    public function process(
        Message\ServerRequestInterface $request,
        Server\RequestHandlerInterface $handler
    ): Message\ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            return Assert::isRequestHandler($this->container->get(Kernel::EXCEPTION_HANDLER_SERVICE))
                ->handle(
                    $request
                        ->withAttribute(Kernel::ERROR_TYPE, 500)
                        ->withAttribute(Kernel::EXCEPTION, $exception)
                );
        }
    }
}
