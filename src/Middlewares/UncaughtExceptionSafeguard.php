<?php

declare(strict_types=1);

namespace Jelly\Middlewares;

use Psr\Http\Message;
use Psr\Http\Server;
use Throwable;
use function error_log;

/**
 * A reusable middleware for tracking uncaught exceptions and optionally
 * send the stack traces in the HTTP response.
 */
final class UncaughtExceptionSafeguard implements Server\MiddlewareInterface
{
    private readonly Message\ResponseFactoryInterface $responseFactory;
    private readonly Message\StreamFactoryInterface $streamFactory;
    private readonly bool $hide;

    public function __construct(
        Message\ResponseFactoryInterface $responseFactory,
        Message\StreamFactoryInterface   $streamFactory,
        bool                             $hide = true
    )
    {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->hide = $hide;
    }

    public function process(Message\ServerRequestInterface $request, Server\RequestHandlerInterface $handler): Message\ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $t) {
            error_log((string)$t);

            $response = $this->responseFactory
                ->createResponse(500);

            return $this->hide ?
                $response :
                $response
                    ->withHeader('Content-Type', 'text/plain')
                    ->withBody($this->streamFactory->createStream((string)$t));
        }
    }
}
