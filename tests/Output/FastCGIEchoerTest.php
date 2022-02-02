<?php

declare(strict_types=1);

namespace ABC\Tests\Output;

use ABC\Output\FastCGIEchoer;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use function headers_list;
use function ob_get_clean;
use function ob_start;

final class FastCGIEchoerTest extends TestCase
{
    /**
     * This test has to run in a separate process to avoid a
     * "Cannot modify header information - headers already sent" error
     *
     * @runInSeparateProcess
     */
    public function testRenderOfSimpleResponse(): void
    {
        ob_start();

        FastCGIEchoer::echo(new Response(200, ['Content-Type' => ['text/plain'], 'Content-Length' => ['6']], 'Hello.'));

        self::assertSame('Hello.', ob_get_clean());

        // Unfortunately this cannot be correctly tested in the CLI SAPI
        // because the header and headers_list functions are both disabled.
        self::assertSame([], headers_list());
    }
}
