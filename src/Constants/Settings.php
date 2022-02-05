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
     * The default chunk size is 8 MiB (8 * 1024^2 bytes).
     *
     * Bear in mind that the default memory_limit directive for the PHP-FPM SAPI
     * is just 128MiB. Therefore, if you set a high chunk size and try to send a
     * large HTTP response your PHP process might attempt to allocate more memory than
     * it is allowed and crash unceremoniously.
     *
     * @see https://www.php.net/manual/en/ini.core.php#ini.memory-limit
     */
    case ECHO_CHUNK_SIZE = 'abc.settings.chunk_size';
}
