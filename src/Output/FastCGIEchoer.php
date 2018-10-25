<?php

declare(strict_types=1);

namespace ABC\Output;

use Psr\Http\Message;

/**
 * @internal
 */
final class FastCGIEchoer
{
    /**
     * Echoes any PSR7-compatible Response back to a FastCGI client
     * according to the RFC 7230 HTTP Message Format specification.
     *
     * @see https://tools.ietf.org/html/rfc7230#section-3
     */
    public static function echo(Message\ResponseInterface $response): void
    {
        \header(\sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        ));

        foreach ($response->getHeaders() as $name => $values) {
            \header(\sprintf(
                '%s: %s',
                $name,
                \implode(', ', $values)
            ));
        }

        echo (string) $response->getBody();
    }
}
