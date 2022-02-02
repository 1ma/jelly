<?php

declare(strict_types=1);

namespace ABC\Util;

use InvalidArgumentException;
use Psr\Http\Server;
use TypeError;

/**
 * @internal
 */
final class Assert
{
    /**
     * @throws InvalidArgumentException
     */
    public static function true(bool $condition, string $message = ''): void
    {
        if (!$condition) {
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * The purpose of this assertion is passing it "mystery objects" retrieved
     * from the container to make sure that they are RequestHandlerInterfaces.
     *
     * @throws TypeError
     */
    public static function isARequestHandler(Server\RequestHandlerInterface $handler): Server\RequestHandlerInterface
    {
        return $handler;
    }
}
