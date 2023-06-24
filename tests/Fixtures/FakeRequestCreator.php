<?php

declare(strict_types=1);

namespace Jelly\Tests\Fixtures;

use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Http\Message\ServerRequestInterface;

final class FakeRequestCreator implements ServerRequestCreatorInterface
{
    private ServerRequestInterface $request;

    public function __construct(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    public function fromGlobals(): ServerRequestInterface
    {
        return $this->request;
    }

    public function fromArrays(array $server, array $headers = [], array $cookie = [], array $get = [], array $post = null, array $files = [], $body = null): ServerRequestInterface
    {
        return $this->request;
    }

    public static function getHeadersFromServer(array $server): array
    {
        return [];
    }
}
