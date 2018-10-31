<?php

declare (strict_types=1);

namespace ABC\Handler;

use ABC\Constants;
use Nyholm\Psr7\Response;
use Psr\Http\Message;
use Psr\Http\Server;

/**
 * Debug exception handler for development environments.
 */
final class VerboseExceptionHandler implements Server\RequestHandlerInterface
{
    public function handle(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        /** @var \Throwable $exception */
        $exception = $request->getAttribute(Constants::EXCEPTION);

        $body = \sprintf(
            "Exception Type: %s\nMessage: %s\nStack Trace:\n## %s(%s)\n%s\n",
            \get_class($exception),
            $exception->getMessage(),
            $exception->getFile(), $exception->getLine(),
            $exception->getTraceAsString()
        );

        $headers = [
            'Content-Type' => 'text/plain',
            'Content-Length' => \strlen($body)
        ];

        return new Response(500, $headers, $body);
    }
}
