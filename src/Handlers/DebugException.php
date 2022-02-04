<?php

declare(strict_types=1);

namespace ABC\Handlers;

use ABC\Constants;
use Psr\Http\Message;
use Psr\Http\Server;
use Throwable;
use function get_class;
use function sprintf;

/**
 * ABC's default handler for debugging uncaught exceptions during development.
 */
final class DebugException implements Server\RequestHandlerInterface
{
    private readonly Message\ResponseFactoryInterface $responseFactory;
    private readonly Message\StreamFactoryInterface $streamFactory;

    public function __construct(
        Message\ResponseFactoryInterface $responseFactory,
        Message\StreamFactoryInterface $streamFactory
    )
    {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    public function handle(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        /** @var Throwable $exception */
        $exception = $request->getAttribute(Constants\Attributes::EXCEPTION->value);

        $body = sprintf(
            "Exception Type: %s\nMessage: %s\nStack Trace:\n#! %s(%s)\n%s\n",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(), $exception->getLine(),
            $exception->getTraceAsString()
        );

        return $this->responseFactory->createResponse(500)
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->streamFactory->createStream($body));
    }
}
