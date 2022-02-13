<?php

declare(strict_types=1);

namespace Jelly\Middlewares;

use Psr\Http\Message;
use Psr\Http\Server;
use Throwable;
use function error_log;

/**
 * A reusable middleware for stopping the propagation of uncontrolled
 * exceptions and optionally send their stack traces in the HTTP response.
 */
final class CrashFailSafe implements Server\MiddlewareInterface
{
    private readonly Message\ResponseInterface $baseResponse;
    private readonly Message\StreamFactoryInterface $streamFactory;
    private readonly bool $hide;

    public function __construct(
        Message\ResponseInterface      $baseResponse,
        Message\StreamFactoryInterface $streamFactory,
        bool                           $hide = true
    )
    {
        $this->baseResponse = $baseResponse;
        $this->streamFactory = $streamFactory;
        $this->hide = $hide;
    }

    public function process(Message\ServerRequestInterface $request, Server\RequestHandlerInterface $handler): Message\ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $t) {
            error_log((string)$t);

            return $this->hide ?
                $this->baseResponse :
                $this->baseResponse
                    ->withHeader('Content-Type', 'text/plain')
                    ->withBody($this->streamFactory->createStream((string)$t));
        }
    }
}
