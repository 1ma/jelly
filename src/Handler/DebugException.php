<?php

declare (strict_types=1);

namespace ABC\Handler;

use ABC\Constants;
use Psr\Http\Message;
use Psr\Http\Server;

/**
 * ABC's default handler for debugging uncaught exceptions during development.
 */
final class DebugException implements Server\RequestHandlerInterface
{
    /**
     * @var Message\ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var Message\StreamFactoryInterface
     */
    private $streamFactory;

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
        /** @var \Throwable $exception */
        $exception = $request->getAttribute(Constants::EXCEPTION);

        $body = \sprintf(
            "Exception Type: %s\nMessage: %s\nStack Trace:\n#! %s(%s)\n%s\n",
            \get_class($exception),
            $exception->getMessage(),
            $exception->getFile(), $exception->getLine(),
            $exception->getTraceAsString()
        );

        return $this->responseFactory->createResponse(500)
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->streamFactory->createStream($body));
    }
}
