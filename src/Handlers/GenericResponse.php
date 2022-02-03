<?php

declare(strict_types=1);

namespace ABC\Handlers;

use Psr\Http\Message;
use Psr\Http\Server;

final class GenericResponse implements Server\RequestHandlerInterface
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
