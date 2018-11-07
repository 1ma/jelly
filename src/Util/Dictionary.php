<?php

declare(strict_types=1);

namespace ABC\Util;

/**
 * Instances of the Dictionary class are used to keep track of which tags
 * are applied to each request handler, and which middleware service names
 * are associated to each tag.
 *
 * @internal
 */
final class Dictionary
{
    /**
     * Map of handlers and their tags
     *
     * @example [
     *   'landing_page' => [
     *     'landing_page' => null,
     *     'all' => null
     *   ],
     *   'dashboard_page' => [
     *     'dashboard_page' => null,
     *     'secured' => null,
     *     'all' => null
     * ]
     * ]
     */
    private $handlers = [];

    /**
     * Map of tags and their service lists.
     *
     * @example [
     *   'landing_page' => [],
     *   'dashboard_page' => ['access_recorder'],
     *   'secured' => ['basic_auth'],
     *   'all' => ['content_length', 'security_headers']
     * ]
     */
    private $tags = [];

    /**
     * Attach a $tag to the given $handler.
     */
    public function tag(string $handler, string $tag): void
    {
        if (!isset($this->tags[$tag])) {
            $this->tags[$tag] = [];
        }

        $this->handlers[$handler][$tag] = null;
    }

    /**
     * Append a $middleware service name to the list of
     * services associated with the given $tag.
     */
    public function push(string $tag, string $middleware): void
    {
        if (!isset($this->tags[$tag])) {
            $this->tags[$tag] = [$middleware];
        } else {
            $this->tags[$tag][] = $middleware;
        }
    }

    /**
     * Return the list of middleware service names
     * associated with the given $handler.
     *
     * @example 'dashboard_page' => ['access_recorder', 'basic_auth', 'content_length', 'security_headers']
     */
    public function lookup(string $handler): array
    {
        if (!isset($this->handlers[$handler])) {
            return [];
        }

        $services = [];
        foreach ($this->handlers[$handler] as $tag => $_) {
            foreach ($this->tags[$tag] as $middleware) {
                $services[] = $middleware;
            }
        }

        return $services;
    }
}
