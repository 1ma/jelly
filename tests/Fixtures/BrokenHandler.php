<?php

declare(strict_types=1);

namespace Jelly\Tests\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A fixture request handler that simulates a runtime error.
 */
final class BrokenHandler implements RequestHandlerInterface
{
    public readonly \RuntimeException $exception;

    public function __construct()
    {
        $this->exception = new \RuntimeException('Whoops!');
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw $this->exception;
    }
}
