<?php

declare(strict_types=1);

namespace Jelly\Internal;

use Jelly\Constants;
use function array_filter;
use function array_merge;
use function array_unique;

/**
 * @internal
 */
final class MiddlewareChainResolver
{
    private array $handlers;
    private array $localMiddlewares;
    private array $globalMiddlewares;

    public function __construct()
    {
        $this->handlers = [
            Constants\Services::NOT_FOUND_HANDLER->value => [],
            Constants\Services::BAD_METHOD_HANDLER->value => []
        ];

        $this->localMiddlewares = [];
        $this->globalMiddlewares = [];
    }

    public function pushHandler(string $name, string ...$groups): void
    {
        $this->handlers[$name] = array_merge(
            [$name],
            array_filter(
                array_unique($groups),
                static function (string $group) use ($name): bool {
                    return $group !== $name;
                }
            )
        );
    }

    public function pushLocalMiddleware(string $name, string ...$groups): void
    {
        foreach (array_unique($groups) as $group) {
            $this->localMiddlewares[$group][] = $name;
        }
    }

    public function pushGlobalMiddleware(string $name): void
    {
        $this->globalMiddlewares[] = $name;
    }

    /**
     * @return string[]
     */
    public function resolve(string $name): array
    {
        $chain = [];

        foreach ($this->handlers[$name] ?? [] as $group) {
            foreach ($this->localMiddlewares[$group] ?? [] as $middleware) {
                $chain[] = $middleware;
            }
        }

        return array_merge($chain, $this->globalMiddlewares);
    }
}
