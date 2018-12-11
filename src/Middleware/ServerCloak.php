<?php

declare(strict_types=1);

namespace ABC\Middleware;

use Psr\Http\Message;
use Psr\Http\Server;

/**
 * A middleware that removes the X-Powered-By header from
 * the response and overwrites Server with the value given
 * at construction time.
 */
final class ServerCloak implements Server\MiddlewareInterface
{
    /**
     * @var string
     */
    private $serverName;

    public function __construct(string $serverName)
    {
        $this->serverName = $serverName;
    }

    /**
     * {@inheritdoc}
     */
    public function process(Message\ServerRequestInterface $request, Server\RequestHandlerInterface $handler): Message\ResponseInterface
    {
        // Headers cannot be added nor removed from
        // the response if they have already been sent.
        if(\headers_sent()) {
            return $handler->handle($request);
        }

        \header_remove('X-Powered-By');

        return $handler->handle($request)
            ->withHeader('Server', $this->serverName);
    }
}
