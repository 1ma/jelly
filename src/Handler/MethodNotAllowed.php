<?php

declare(strict_types=1);

namespace ABC\Handler;

use ABC\Constants;
use Psr\Http\Message;
use Psr\Http\Server;
use function implode;

/**
 * ABC's default handler for HTTP 405 errors.
 *
 * It is just an empty response with the standard 'Allow' header.
 */
final class MethodNotAllowed implements Server\RequestHandlerInterface
{
    private readonly Message\ResponseFactoryInterface $factory;

    public function __construct(Message\ResponseFactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    public function handle(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        return $this->factory->createResponse(405)
            ->withHeader('Allow', implode(', ', $request->getAttribute(Constants::ALLOWED_METHODS)));
    }
}
