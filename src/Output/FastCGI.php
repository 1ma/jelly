<?php

declare(strict_types=1);

namespace ABC\Output;

use ABC\Output;
use Psr\Http\Message;
use function fastcgi_finish_request;
use function function_exists;
use function header;
use function implode;
use function sprintf;

/**
 * Sends a PSR7-compatible Response back to a FastCGI client
 * according to the RFC 7230 HTTP Message Format specification.
 *
 * @see https://tools.ietf.org/html/rfc7230#section-3
 */
final class FastCGI implements Output
{
    /**
     * The default behaviour is emitting the response in chunks of at most 8 MiB at a time.
     *
     * This is to avoid exhausting the memory available to the PHP process.
     * By default, the memory_limit INI directive is 128MB so 8 MiB should be
     * low enough in virtually all cases.
     */
    private const DEFAULT_CHUNK_SIZE = 8 * (1024 ** 2);

    private readonly int $chunkSize;
    private readonly bool $endFastCGI;

    public function __construct(int $chunkSize = self::DEFAULT_CHUNK_SIZE, bool $endFastCGI = true)
    {
        $this->chunkSize = $chunkSize;
        $this->endFastCGI = $endFastCGI && function_exists('fastcgi_finish_request');
    }

    public function send(Message\ResponseInterface $response): void
    {
        header(sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        ));

        foreach ($response->getHeaders() as $name => $values) {
            header(sprintf(
                '%s: %s',
                $name,
                implode(', ', $values)
            ));
        }

        $stream = $response->getBody();
        $stream->rewind();

        while ('' !== $chunk = $stream->read($this->chunkSize)) {
            echo $chunk;
        }

        $stream->close();

        if ($this->endFastCGI) {
            fastcgi_finish_request();
        }
    }
}
