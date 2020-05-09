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
     * The response is emitted in chunks of at most 8MB at a time.
     *
     * This is to avoid exhausting the memory available to the PHP process.
     * By default the memory_limit INI directive is 128MB so 8MB should be
     * low enough in virtually all cases.
     */
    private const CHUNK_SIZE = 8 * 1024 * 1024;

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

        $stream = $response->getBody();
        $stream->rewind();

        while ('' !== $chunk = $stream->read(self::CHUNK_SIZE)) {
            echo $chunk;
        }

        $stream->close();
    }
}
