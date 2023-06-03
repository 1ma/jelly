<?php

declare(strict_types=1);

namespace Jelly\Tests\Unit\Handlers;

use Jelly\Handlers;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class StaticResponseTest extends TestCase
{
    public function testStaticResponse(): void
    {
        $handler = new Handlers\StaticResponse($response = new Response(418));

        self::assertSame($response, $handler->handle(new ServerRequest('GET', '/')));
    }
}
