<?php

declare(strict_types=1);

namespace ABC\Tests\Util;

use ABC\Util\Dictionary;
use PHPUnit\Framework\TestCase;

final class DictionaryTest extends TestCase
{
    public function testResolveNonDefinedHandler(): void
    {
        $d = new Dictionary;

        self::assertSame([], $d->lookup('landing_page'));
    }

    public function testNormalOperation(): void
    {
        $c = new Dictionary();

        $c->tag('dashboard_page', 'protected_area');
        $c->tag('dashboard_page', 'common');
        $c->tag('landing_page', 'common');

        $c->push('protected_area', 'basic_auth_middleware');
        $c->push('common', 'content_length_middleware');

        self::assertSame(['basic_auth_middleware', 'content_length_middleware'], $c->lookup('dashboard_page'));
        self::assertSame(['content_length_middleware'], $c->lookup('landing_page'));
    }

    public function testTagInReverseOrder(): void
    {
        $c = new Dictionary();

        $c->push('protected_area', 'basic_auth_middleware');
        $c->push('common', 'content_length_middleware');

        $c->tag('landing_page', 'common');
        $c->tag('dashboard_page', 'protected_area');
        $c->tag('dashboard_page', 'common');


        self::assertSame(['basic_auth_middleware', 'content_length_middleware'], $c->lookup('dashboard_page'));
        self::assertSame(['content_length_middleware'], $c->lookup('landing_page'));
    }
}
