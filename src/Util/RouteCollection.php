<?php

declare(strict_types=1);

namespace ABC\Util;

use FastRoute;

/**
 * @internal
 */
final class RouteCollection extends FastRoute\RouteCollector implements FastRoute\DataGenerator
{
    public function __construct()
    {
        parent::__construct(
            new FastRoute\RouteParser\Std,
            new FastRoute\DataGenerator\GroupCountBased
        );
    }
}
