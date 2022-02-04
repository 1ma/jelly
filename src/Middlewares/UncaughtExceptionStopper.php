<?php

declare(strict_types=1);

namespace ABC\Middlewares;

use ABC\Constants;
use ABC\Internal\Assert;
use LogicException;
use Psr\Container;
use Psr\Http\Message;
use Psr\Http\Server;
use Throwable;
use TypeError;

final class UncaughtExceptionStopper implements Server\MiddlewareInterface
{
    private readonly Container\ContainerInterface $container;

    public function __construct(Container\ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @throws LogicException If EXCEPTION_HANDLER is not in the container or
     *                        is not an instance of RequestHandlerInterface.
     */
    public function process(
        Message\ServerRequestInterface $request,
        Server\RequestHandlerInterface $handler
    ): Message\ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $t) {
            try {
                $fallbackHandler = Assert::isRequestHandler($this->container->get(Constants::EXCEPTION_HANDLER->value));
                return $fallbackHandler->handle($request->withAttribute(Constants::EXCEPTION->value, $t));
            } catch (Container\ContainerExceptionInterface|TypeError $e) {
                throw new LogicException(message: $e->getMessage(), previous: $e);
            }
        }
    }
}
