<?php

declare(strict_types=1);

namespace ABC\Middlewares;

use Psr\Http\Message;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use function get_class;
use function sprintf;

/**
 * A reusable middleware for debugging uncaught exceptions during development.
 */
final class UncaughtExceptionDebugger implements Server\MiddlewareInterface
{
    private readonly Message\ResponseFactoryInterface $responseFactory;
    private readonly Message\StreamFactoryInterface $streamFactory;

    public function __construct(
        Message\ResponseFactoryInterface $responseFactory,
        Message\StreamFactoryInterface   $streamFactory
    )
    {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $t) {
            return $this->responseFactory
                ->createResponse(500)
                ->withHeader('Content-Type', 'text/plain')
                ->withBody($this->streamFactory->createStream(
                    sprintf(
                        "Exception Type: %s\nMessage: %s\nStack Trace:\n#! %s(%s)\n%s\n",
                        get_class($t),
                        $t->getMessage(),
                        $t->getFile(), $t->getLine(),
                        $t->getTraceAsString()
                    )
                ));
        }
    }
}
