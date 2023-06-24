<?php

declare(strict_types=1);

namespace Jelly\Internal;

use FastRoute;

/**
 * A stupid wrapper over FastRoute's RouteCollector to simplify its
 * usage in the Jelly class. Inexplicably the RouteCollector does not
 * implement DataGenerator even though it has all the methods of said
 * interface.
 *
 * @internal
 */
final class RouteCollection extends FastRoute\RouteCollector implements FastRoute\DataGenerator
{
    public function __construct()
    {
        parent::__construct(
            new FastRoute\RouteParser\Std(),
            new FastRoute\DataGenerator\GroupCountBased()
        );
    }
}
