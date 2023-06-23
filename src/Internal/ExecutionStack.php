<?php

declare(strict_types=1);

namespace Jelly\Internal;

use Psr\Http\Message;
use Psr\Http\Server;

/**
 * @internal
 */
final readonly class ExecutionStack implements Server\RequestHandlerInterface
{
    private Server\MiddlewareInterface $middleware;
    private Server\RequestHandlerInterface $next;

    public static function compose(Server\RequestHandlerInterface $core, Server\MiddlewareInterface ...$layers): Server\RequestHandlerInterface
    {
        $stack = $core;

        foreach ($layers as $layer) {
            $stack = new self($stack, $layer);
        }

        return $stack;
    }

    private function __construct(Server\RequestHandlerInterface $next, Server\MiddlewareInterface $middleware)
    {
        $this->next = $next;
        $this->middleware = $middleware;
    }

    public function handle(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        return $this->middleware->process($request, $this->next);
    }
}
