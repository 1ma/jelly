<?php

declare(strict_types=1);

namespace Jelly\Tests\Fixtures;

use Jelly\Constants;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A fixture request handler that simulates a successful request.
 */
final class HelloHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $name = $request->getAttribute(Constants\Attributes::ARGS->value)['name'] ?? null;

        return new Response(200, ['Content-Type' => 'text/plain'], null === $name ? 'Hello.' : "Hello $name.");
    }
}
