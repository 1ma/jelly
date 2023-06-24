<?php

declare(strict_types=1);

namespace Jelly\Constants;

/**
 * Attribute Keys.
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
    case HANDLER = 'jelly.attributes.handler';

    /**
     * On successful dynamic route matches the framework will set a request attribute
     * with this name to the array of values that matched the route.
     *
     * @example "GET /hello/jelly" matches "GET /hello/{name}", so ARGS equals ['name' => 'jelly']
     *
     * The attribute is a (possibly empty) hashmap of string => string entries.
     */
    case ARGS = 'jelly.attributes.args';

    /**
     * On successful route matches the framework will set a request attribute
     * with this name to the list of middleware service names that are
     * to process this request. You can use this information to check that the
     * middlewares that ended up wrapping a given request handler are exactly
     * the ones you intended.
     *
     * @example 'dashboard', 'content-length' and 'basic-auth' are names of service definitions in the container:
     *  + You registered a route named 'dashboard' with an extra group named 'secured'
     *  + You wrapped the whole app in a 'content-length' global middleware
     *  + You tagged the 'secured' group with a 'basic-auth' middleware
     *  + If a request matches 'dashboard', its MIDDLEWARE_CHAIN attribute equals ['content-length', 'basic-auth']
     *    and the full execution order is 'content-length' -> 'basic-auth' -> 'dashboard' -> 'basic-auth' -> 'content-length'
     *
     * The attribute is a (possible empty) list of strings.
     */
    case MIDDLEWARE_CHAIN = 'jelly.attributes.middleware_chain';

    /**
     * On Method Not Allowed errors (HTTP 405) the framework will set a request attribute
     * with this name to the list of HTTP methods that are allowed for the matched route.
     *
     * @example "GET /form" partial-matches "POST /form", and ALLOWED_METHODS equals ['POST']
     *
     * The attribute is a non-empty list of strings.
     */
    case ALLOWED_METHODS = 'jelly.attributes.allowed_methods';
}
