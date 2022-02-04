<?php

declare(strict_types=1);

namespace ABC;

enum Constants: string
{
    /***********************************************************************
     * Attribute Keys                                                      *
     *                                                                     *
     * These are the names of the attributes that the framework may set on *
     * the request on certain situations.                                  *
     ***********************************************************************/

    /**
     * On successful route matches the framework will set a request attribute
     * with this name to the service name of the handler that the router resolved.
     *
     * The attribute is always a string.
     */
    case HANDLER = 'abc.key.handler';

    /**
     * On successful dynamic route matches the framework will set a request attribute
     * with this name to the array of values that matched the route.
     *
     * @example "GET /hello/tron" matches "GET /hello/{name}", so ARGS equals ['name' => 'tron']
     *
     * The attribute is a (possibly empty) hashmap of string => string entries.
     */
    case ARGS = 'abc.key.args';

    /**
     * On Method Not Allowed errors (HTTP 405) the framework will set a request attribute
     * with this name to the list of HTTP methods that are allowed for the matched route.
     *
     * @example "GET /form" partial-matches "POST /form", and ALLOWED_METHODS equals ['POST']
     *
     * The attribute is a non-empty list of strings.
     */
    case ALLOWED_METHODS = 'abc.key.allowed_methods';

    /**
     * If an uncaught exception slips through your application the framework will catch it
     * and reroute the request to the EXCEPTION_HANDLER service. In addition, the framework
     * will set a request attribute with the caught exception under this name.
     *
     * The attribute is a descendant of PHP's builtin Throwable interface.
     */
    case EXCEPTION = 'abc.key.exception';

    /*****************************************************************************
     * Mandatory Services                                                        *
     *                                                                           *
     * These are service names that must be present in the PSR-11 container when *
     * you construct the framework object, otherwise the constructor will throw  *
     * a LogicException.                                                         *
     *****************************************************************************/

    /**
     * A service that must resolve to a PSR-15 RequestHandlerInterface.
     *
     * This handler will process requests that trigger an HTTP 404 error,
     * meaning that they didn't match any route.
     *
     * At the very least the emitted response should have the HTTP 404 status code.
     */
    case NOT_FOUND_HANDLER = 'abc.service.not_found_handler';

    /**
     * A service that must resolve to a PSR-15 RequestHandlerInterface.
     *
     * This handler will process requests that trigger an HTTP 405 error,
     * meaning that they matched some route but the HTTP method was not allowed.
     *
     * The request will contain the ALLOWED_METHODS attribute with the list of
     * methods that are actually allowed for the route that matched.
     *
     * At the very least the emitted response should have the HTTP 405 status code
     * and the 'Allow' header.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Allow
     */
    case BAD_METHOD_HANDLER = 'abc.service.bad_method_handler';

    /**
     * A service that must resolve to a PSR-15 RequestHandlerInterface.
     *
     * This is a fallback handler that is called when an uncaught exception slips
     * through the user code in order to prevent it from crashing the whole process.
     *
     * The request will contain the EXCEPTION attribute with the uncaught
     * exception that triggered the handler.
     *
     * These handlers should emit an HTTP 500 response and track the
     * exception however they see fit.
     */
    case EXCEPTION_HANDLER = 'abc.service.exception_handler';

    /*****************************************************************************
     * Framework Knobs                                                           *
     *                                                                           *
     * These are values or services that can be defined in the container but are *
     * optional. If present they allow tuning some framework settings.           *
     *****************************************************************************/

    /**
     * A service that if present must resolve to a positive integer.
     *
     * The default chunk size is 8 MiB (8388608 bytes).
     *
     * Bear in mind that the default memory_limit directive for the PHP-FPM SAPI
     * is 128MiB. Therefore, if you set a high chunk size your PHP process might
     * try to consume more memory than it is allowed and crash unceremoniously.
     *
     * @see https://www.php.net/manual/en/ini.core.php#ini.memory-limit
     */
    case ECHO_CHUNK_SIZE = 'abc.option.chunk_size';

    /**
     * A service that if present must resolve to a string that references a writable
     * path in the filesystem.
     *
     * The default cache path is null (i.e. the framework doesn't use this feature).
     *
     * When present the framework will write an efficient, pre-compiled routing map
     * on that location. The framework will attempt to create both the file AND the
     * directory structure if any of them are missing.
     *
     * If the string is not a valid path, does not end in '.php' or does not point
     * to a writable location for the PHP process the framework will emit error
     * messages into STDERR on each request.
     *
     * @example /tmp/my-api/cached-routes.php
     */
    case ROUTER_CACHE_PATH = 'abc.option.router_cache_path';
}
