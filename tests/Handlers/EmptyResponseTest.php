<?php

declare(strict_types=1);

namespace ABC\Tests\Handlers;

use ABC\Handlers;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class EmptyResponseTest extends TestCase
{
    public function testIt(): void
    {
        $emptyResponseHandler = new Handlers\EmptyResponse(new Psr17Factory, 418);

        $response = $emptyResponseHandler->handle(new ServerRequest('GET', '/'));

        self::assertSame(418, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }
}
