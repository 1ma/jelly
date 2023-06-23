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
final readonly class CrashFailsafe implements Server\MiddlewareInterface
{
    private Message\ResponseInterface $staticResponse;
    private Message\ResponseFactoryInterface $responseFactory;
    private bool $hide;

    public function __construct(
        Message\ResponseInterface        $staticResponse,
        Message\ResponseFactoryInterface $responseFactory,
        bool                             $hide = true
    )
    {
        $this->staticResponse = $staticResponse;
        $this->responseFactory = $responseFactory;
        $this->hide = $hide;
    }

    public function process(Message\ServerRequestInterface $request, Server\RequestHandlerInterface $handler): Message\ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $t) {
            error_log($stackTrace = (string)$t);

            return $this->hide ?
                $this->staticResponse :
                $this->responseFactory->createResponse(500)
                    ->withHeader('Content-Type', 'text/plain')
                    ->withBody($this->responseFactory->createStream($stackTrace));
        }
    }
}
