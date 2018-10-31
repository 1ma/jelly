<?php

declare(strict_types=1);

namespace ABC\Util;

use Psr\Http\Message;
use Psr\Http\Server;

/**
 * @internal
 */
final class Seam implements Server\RequestHandlerInterface
{
    /**
     * @var Server\MiddlewareInterface
     */
    private $middleware;

    /**
     * @var Server\RequestHandlerInterface
     */
    private $next;

    public function __construct(Server\MiddlewareInterface $middleware, Server\RequestHandlerInterface $next)
    {
        $this->middleware = $middleware;
        $this->next = $next;
    }

    public static function compose(Server\RequestHandlerInterface $bottom, Server\MiddlewareInterface ...$middlewares)
    {
        $stack = $bottom;

        foreach ($middlewares as $middleware) {
            $stack = new self($middleware, $stack);
        }

        return $stack;
    }

    public function handle(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        return $this->middleware->process($request, $this->next);
    }
}
