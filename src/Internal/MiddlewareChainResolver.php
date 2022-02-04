<?php

declare(strict_types=1);

namespace ABC\Internal;

/**
 * @internal
 */
final class MiddlewareChainResolver
{
    private array $globalMiddlewares;

    public function __construct()
    {
        $this->globalMiddlewares = [];
    }

    public function pushGlobalMiddleware(string $service): void
    {
        $this->globalMiddlewares[] = $service;
    }

    /**
     * @return string[]
     */
    public function resolve(string $service): array
    {
        return $this->globalMiddlewares;
    }
}
