<?php

declare(strict_types=1);

namespace ABC\Handler;

use Psr\Http\Message;
use Psr\Http\Server;

final class MiddlewareStack implements Server\RequestHandlerInterface
{
    private readonly Server\MiddlewareInterface $middleware;
    private readonly Server\RequestHandlerInterface $next;

    public static function compose(Server\RequestHandlerInterface $bottom, Server\MiddlewareInterface ...$decorators): Server\RequestHandlerInterface
    {
        $stack = $bottom;

        foreach ($decorators as $decorator) {
            $stack = new self($stack, $decorator);
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
