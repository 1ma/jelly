<?php

declare(strict_types=1);

namespace ABC;

final class Constants
{
    const NOT_FOUND_HANDLER = 'abc.404';
    const BAD_METHOD_HANDLER = 'abc.405';
    const EXCEPTION_HANDLER = 'abc.500';

    const ERROR_TYPE = 'abc.error';
    const ALLOWED_METHODS = 'abc.allowed_methods';
    const EXCEPTION = 'abc.exception';

    const ARGS = 'abc.args';
    const HANDLER = 'abc.handler';
}
