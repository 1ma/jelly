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
    public static function hasService(ContainerInterface $container, string $service, string $message = ''): void
    {
        if (!$container->has($service)) {
            throw new LogicException($message);
        }
    }

    /**
     * The purpose of this assertion is passing it "mystery objects" retrieved
     * from the container to make sure that they are RequestHandlerInterfaces.
     *
     * @throws TypeError
     */
    public static function isRequestHandler(RequestHandlerInterface $handler): RequestHandlerInterface
    {
        return $handler;
    }
}