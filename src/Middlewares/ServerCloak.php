<?php

declare(strict_types=1);

namespace Jelly\Middlewares;

use Psr\Http\Message;
use Psr\Http\Server;

/**
 * A middleware that removes the X-Powered-By header from
 * the response and overwrites Server with the value given
 * at construction time.
 */
final readonly class ServerCloak implements Server\MiddlewareInterface
{
    private string $serverName;

    public function __construct(string $serverName)
    {
        $this->serverName = $serverName;
    }

    public function process(Message\ServerRequestInterface $request, Server\RequestHandlerInterface $handler): Message\ResponseInterface
    {
        header_remove('X-Powered-By');

        return $handler->handle($request)
            ->withHeader('Server', $this->serverName);
    }
}
