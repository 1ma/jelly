<?php

declare(strict_types=1);

namespace Jelly\Handlers;

use Psr\Http\Message;
use Psr\Http\Server;

final class StaticResponse implements Server\RequestHandlerInterface
{
    private readonly Message\ResponseInterface $response;

    public function __construct(Message\ResponseInterface $response)
    {
        $this->response = $response;
    }

    public function handle(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        return $this->response;
    }
}
