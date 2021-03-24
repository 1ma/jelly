<?php

declare(strict_types=1);

namespace ABC;

final class Constants
{
    /**
     * This is a service name that the framework expects to map to a RequestHandlerInterface
     * that will handle Not Found (HTTP 404) errors.
     */
    public const NOT_FOUND_HANDLER = 'abc.404';

    /**
     * This is a service name that the framework expects to map to a RequestHandlerInterface
     * that will handle Method Not Allowed (HTTP 405) errors.
     */
    public const BAD_METHOD_HANDLER = 'abc.405';

    /**
     * This is a service name that the framework expects to map to a RequestHandlerInterface
     * that will handle any uncaught exceptions thrown by the application.
     */
    public const EXCEPTION_HANDLER = 'abc.500';

    /**
     * If an uncaught exception is captured, a Method Not Found error occurs, or a
     * Not Found error occurs, the framework will add an error code to the request
     * under this attribute key.
     *
     * It will either be 500, 405 or 404 respectively.
     */
    public const ERROR_TYPE = 'abc.error';

    /**
     * If a Method Not Allowed error occurs, the framework will attach the list of valid
     * HTTP verbs to the request under this attribute key.
     *
     * A RequestHandlerInterface stored in the container as Constants::BAD_METHOD_HANDLER
     * can rely on the fact that the incoming request will have this attribute, and its
     * value will always be a non-empty array of strings, such as ['GET', 'PUT', 'DELETE'].
     */
    public const ALLOWED_METHODS = 'abc.allowed_methods';

    /**
     * If the framework captures an uncaught exception it will be attached to the request
     * under this attribute key.
     *
     * A RequestHandlerInterface stored in the container as Constants::EXCEPTION_HANDLER
     * can rely on the fact that the incoming request will have this attribute, and its
     * value will always be an exception.
     */
    public const EXCEPTION = 'abc.exception';

    /**
     * On every successfully routed request, the framework will attach its arguments
     * under this attribute key.
     *
     * Arguments are the named placeholders that can be defined in FastRoute's
     * path declarations, such as '/hello/{name}'.
     */
    public const ARGS = 'abc.args';

    /**
     * On every successfully routed request, the framework will attach the service
     * name of the designed request handler under this attribute key.
     */
    public const HANDLER = 'abc.handler';
}
