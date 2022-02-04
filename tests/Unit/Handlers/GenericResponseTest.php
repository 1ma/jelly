<?php

declare(strict_types=1);

namespace ABC\Tests\Unit\Handlers;

use ABC\Handlers;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class GenericResponseTest extends TestCase
{
    public function testIt(): void
    {
        $handler = new Handlers\GenericResponse($response = new Response(418));

        self::assertSame($response, $handler->handle(new ServerRequest('GET', '/')));
    }
}
