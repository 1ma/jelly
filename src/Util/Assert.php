<?php

declare(strict_types=1);

namespace ABC\Util;

use LogicException;
use Psr\Container;
use Psr\Http\Server;
use TypeError;

/**
 * @internal
 */
final class Assert
{
    /**
     * This assertion throws a canonical PSR-11 NotFoundExceptionInterface without
     * actually retrieving (i.e. instantiating) the service from the container.
     *
     * If this exception is thrown it means a mandatory service is missing and this
     * must lead to a fix in the application bootstrapping (hence the base
     * exception is a LogicException).
     *
     * @throws Container\NotFoundExceptionInterface
     */
    public static function hasService(Container\ContainerInterface $container, string $service): void
    {
        if (!$container->has($service)) {
            throw new class
                extends LogicException
                implements Container\NotFoundExceptionInterface {};
        }
    }

    /**
     * The purpose of this method is passing "mystery objects" retrieved from the container
     * to make sure that they are RequestHandlerInterfaces.
     *
     * @throws TypeError
     */
    public static function isARequestHandler(Server\RequestHandlerInterface $handler): Server\RequestHandlerInterface
    {
        return $handler;
    }

    /**
     * The purpose of this method is passing "mystery objects" retrieved from the container
     * to make sure that they are MiddlewareInterfaces.
     *
     * @throws TypeError
     */
    public static function isAMiddleware(Server\MiddlewareInterface $middleware): Server\MiddlewareInterface
    {
        return $middleware;
    }
}
