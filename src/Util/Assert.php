<?php

declare(strict_types=1);

namespace ABC\Util;

use Psr\Http\Server;
use TypeError;

/**
 * @internal
 */
final class Assert
{
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
