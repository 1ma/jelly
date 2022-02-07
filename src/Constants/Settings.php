<?php

declare(strict_types=1);

namespace Jelly\Constants;

/**
 * Framework Settings
 *
 * These are values or services that can be defined in the container but are
 * optional. If present they allow tuning some framework settings.
 */
enum Settings: string
{
    /**
     * The name of a service that if present must resolve to a positive integer.
     *
     * The default chunk size is 8 MiB (8 * 1024^2 bytes).
     *
     * Bear in mind that the default memory_limit directive for the PHP-FPM SAPI
     * is just 128MiB. Therefore, if you set a high chunk size and try to send a
     * large HTTP response your PHP process might attempt to allocate more memory than
     * it is allowed and crash unceremoniously.
     *
     * @see https://www.php.net/manual/en/ini.core.php#ini.memory-limit
     */
    case ECHO_CHUNK_SIZE = 'jelly.settings.chunk_size';

    /**
     * The name of a service that if present must resolve to an instance of
     * Nyholm\Psr7Server\ServerRequestCreatorInterface.
     *
     * Define this service if your project depends on multiple PSR-7 implementations
     * and the framework's automatic pick is not the one you want.
     *
     * @see ServerRequestPicker for examples on how to instantiate this class for
     *                          every major PSR-7 implementation.
     */
    case SERVER_REQUEST_CREATOR = 'jelly.settings.server_request_creator';
}
