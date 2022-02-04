<?php

declare(strict_types=1);

namespace ABC\Handlers;

use Psr\Http\Message;
use Psr\Http\Server;

final class ExecutionStack implements Server\RequestHandlerInterface
{
    private readonly Server\MiddlewareInterface $middleware;
    private readonly Server\RequestHandlerInterface $next;

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
