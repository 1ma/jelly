<?php

declare(strict_types=1);

namespace ABC\Constants;

/**
 * Attribute Keys
 *
 * These are the names of the attribute keys that the framework may set on
 * the request on certain situations.
 */
enum Attributes: string
{
    /**
     * On successful route matches the framework will set a request attribute
     * with this name to the service name of the handler that the router resolved.
     *
     * The attribute is always a string.
     */
    case HANDLER = 'abc.attributes.handler';

    /**
     * On successful dynamic route matches the framework will set a request attribute
     * with this name to the array of values that matched the route.
     *
     * @example "GET /hello/tron" matches "GET /hello/{name}", so ARGS equals ['name' => 'tron']
     *
     * The attribute is a (possibly empty) hashmap of string => string entries.
     */
    case ARGS = 'abc.attributes.args';

    /**
     * On Method Not Allowed errors (HTTP 405) the framework will set a request attribute
     * with this name to the list of HTTP methods that are allowed for the matched route.
     *
     * @example "GET /form" partial-matches "POST /form", and ALLOWED_METHODS equals ['POST']
     *
     * The attribute is a non-empty list of strings.
     */
    case ALLOWED_METHODS = 'abc.attributes.allowed_methods';
}
