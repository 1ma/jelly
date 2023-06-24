<?php

declare(strict_types=1);

namespace Jelly\Constants;

/**
 * Mandatory Services.
 *
 * These are service names that must be present in the PSR-11 container when
 * you construct the framework object, otherwise the constructor will throw
 * a LogicException.
 */
enum Services: string
{
    /**
     * The name of a service that must resolve to a PSR-15 RequestHandlerInterface.
     *
     * This handler will process requests that trigger an HTTP 404 error,
     * meaning that they didn't match any of the routes you defined.
     *
     * At the very least the emitted response should have the HTTP 404 status code.
     */
    case NOT_FOUND_HANDLER = 'jelly.services.not_found_handler';

    /**
     * The name of a service that must resolve to a PSR-15 RequestHandlerInterface.
     *
     * This handler will process requests that trigger an HTTP 405 error,
     * meaning that they matched some route but the HTTP method was not allowed.
     *
     * The request will contain the Attributes::ALLOWED_METHODS attribute with the list
     * of methods that are actually allowed for the route that matched.
     *
     * At the very least the emitted response should have the HTTP 405 status code
     * and the 'Allow' header.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Allow
     */
    case BAD_METHOD_HANDLER = 'jelly.services.bad_method_handler';
}
