<?php

declare(strict_types=1);

namespace Jelly\Tests\Fixtures;

use Psr\Http\Message;
use Psr\Http\Server;

final class TripwireMiddleware implements Server\MiddlewareInterface
{
    public bool $tripped = false;

    public function process(Message\ServerRequestInterface $request, Server\RequestHandlerInterface $handler): Message\ResponseInterface
    {
        $this->tripped = true;

        return $handler->handle($request);
    }
}
