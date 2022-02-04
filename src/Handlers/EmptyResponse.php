<?php

declare(strict_types=1);

namespace ABC\Handlers;

use Psr\Http\Message;
use Psr\Http\Server;

/**
 * ABC's default handler for HTTP 404 and 500 errors.
 *
 * It can be used to return empty HTTP responses where
 * only the status code can vary.
 */
final class EmptyResponse implements Server\RequestHandlerInterface
{
    private readonly Message\ResponseFactoryInterface $factory;
    private readonly int $statusCode;

    public function __construct(Message\ResponseFactoryInterface $factory, int $statusCode)
    {
        $this->factory = $factory;
        $this->statusCode = $statusCode;
    }

    public function handle(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        return $this->factory
            ->createResponse($this->statusCode);
    }
}