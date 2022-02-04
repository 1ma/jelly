<?php

declare(strict_types=1);

namespace ABC\Constants;

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
     * The default chunk size is 8 MiB (8388608 bytes).
     *
     * Bear in mind that the default memory_limit directive for the PHP-FPM SAPI
     * is 128MiB. Therefore, if you set a high chunk size your PHP process might
     * try to consume more memory than it is allowed and crash unceremoniously.
     *
     * @see https://www.php.net/manual/en/ini.core.php#ini.memory-limit
     */
    case ECHO_CHUNK_SIZE = 'abc.settings.chunk_size';

    /**
     * The name of a service that if present must resolve to a string that references
     * a writable path in the filesystem.
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
    case ROUTER_CACHE_PATH = 'abc.settings.router_cache_path';
}
