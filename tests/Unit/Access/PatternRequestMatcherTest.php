<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin\Unit\Access;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Tmonier\SyliusChannelAccessPlugin\Access\PatternRequestMatcher;

final class PatternRequestMatcherTest extends TestCase
{
    /** @return iterable<string, array{string, ?string, bool}> path, route, matches */
    public static function requests(): iterable
    {
        yield 'exact route' => ['/en_US/login', 'sylius_shop_login', true];
        yield 'route pattern' => ['/x', 'app_feed_products', true];
        yield 'other route' => ['/en_US/products/a', 'sylius_shop_product_show', false];
        yield 'path prefix' => ['/feeds/a/b.xml', null, true];
        yield 'exact path' => ['/punchout', null, true];
        yield 'path prefix does not match its sibling' => ['/feedsx/a', null, false];
        yield 'encoded climbing' => ['/feeds/%2e%2e/private', null, false];
        yield 'climbing' => ['/feeds/../private', null, false];
        yield 'doubled slash' => ['/feeds//a', null, false];
        yield 'nothing matches an unknown request' => ['/en_US/anything', null, false];
    }

    #[DataProvider('requests')]
    public function testMatching(string $path, ?string $route, bool $expected): void
    {
        $matcher = new PatternRequestMatcher(['sylius_shop_login', 'app_feed_*'], ['/feeds/*', '/punchout']);
        $request = Request::create($path);
        if (null !== $route) {
            $request->attributes->set('_route', $route);
        }

        self::assertSame($expected, $matcher->matches($request));
    }

    public function testAnEmptyListMatchesNothing(): void
    {
        $request = Request::create('/anything');
        $request->attributes->set('_route', 'anything');

        self::assertFalse((new PatternRequestMatcher([], []))->matches($request));
    }
}
