<?php

declare(strict_types=1);

namespace ABC\Tests\Fixture;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * A fixture request handler that simulates a runtime error.
 */
final class BrokenHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Whoops!');
    }
}
