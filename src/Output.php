<?php

declare(strict_types=1);

namespace ABC;

use Psr\Http\Message\ResponseInterface;

interface Output
{
    public function send(ResponseInterface $response): void;
}
