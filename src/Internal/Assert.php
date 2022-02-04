<?php

declare(strict_types=1);

namespace ABC\Internal;

use LogicException;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TypeError;

/**
 * @internal
 */
final class Assert
{
    /**
     * @throws LogicException
     */
    public static function hasService(ContainerInterface $container, string $service, string $message = null): void
    {
        if (!$container->has($service)) {
            throw new LogicException($message ?? "'$service' must be registered as a service and is not");
        }
    }

    /**
     * @throws TypeError
     */
    public static function isRequestHandler(RequestHandlerInterface $handler): RequestHandlerInterface
    {
        return $handler;
    }
}
