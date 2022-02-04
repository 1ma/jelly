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
        $emptyResponseHandler = new Handlers\GenericResponse(new Response(418));

        $response = $emptyResponseHandler->handle(new ServerRequest('GET', '/'));

        self::assertSame(418, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }
}
