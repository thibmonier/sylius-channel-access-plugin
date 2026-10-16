<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tmonier\SyliusChannelAccessPlugin\DependencyInjection\TmonierSyliusChannelAccessExtension;

final class TmonierSyliusChannelAccessExtensionTest extends TestCase
{
    public function testTheConfiguredExemptionsAreAddedToTheBuiltInOnes(): void
    {
        $container = new ContainerBuilder();
        (new TmonierSyliusChannelAccessExtension())->load([['exempt_paths' => ['/feeds/*'], 'exempt_routes' => ['app_mcp_*']]], $container);

        /** @var list<string> $routes */
        $routes = $container->getParameter('tmonier_channel_access.exempt_routes');
        /** @var list<string> $paths */
        $paths = $container->getParameter('tmonier_channel_access.exempt_paths');

        foreach (['sylius_shop_homepage', 'sylius_shop_login', 'sylius_shop_register', 'app_mcp_*'] as $route) {
            self::assertContains($route, $routes);
        }
        self::assertContains('/feeds/*', $paths);
        self::assertContains('/%sylius_admin.path_name%/*', $paths);
        self::assertNotContains('*', $paths);
    }

    public function testTheConfiguredRestrictionsAreAddedToTheBuiltInOnes(): void
    {
        $container = new ContainerBuilder();
        (new TmonierSyliusChannelAccessExtension())->load([['restricted_routes' => ['app_quote_*'], 'restricted_paths' => ['/api/x/*']]], $container);

        /** @var list<string> $routes */
        $routes = $container->getParameter('tmonier_channel_access.restricted_routes');
        /** @var list<string> $paths */
        $paths = $container->getParameter('tmonier_channel_access.restricted_paths');

        foreach (['sylius_shop_cart_*', 'sylius_shop_checkout_*', 'sylius_shop_order_*', 'app_quote_*'] as $route) {
            self::assertContains($route, $routes);
        }
        self::assertContains('/api/x/*', $paths);
        self::assertContains('%sylius.security.api_shop_route%/orders/*', $paths);
        self::assertContains('%sylius.security.api_shop_route%/orders.*', $paths, 'the format suffixes of the API');
    }

    public function testWithoutOptionsOnlyTheBuiltInRestrictionsApply(): void
    {
        $container = new ContainerBuilder();
        (new TmonierSyliusChannelAccessExtension())->load([], $container);

        self::assertSame(['sylius_shop_cart_*', 'sylius_shop_checkout_*', 'sylius_shop_order_*', 'sylius_shop_payment_request_*'], $container->getParameter('tmonier_channel_access.restricted_routes'));
        self::assertSame(['%sylius.security.api_shop_route%/orders', '%sylius.security.api_shop_route%/orders/*', '%sylius.security.api_shop_route%/orders.*'], $container->getParameter('tmonier_channel_access.restricted_paths'));
    }

    public function testTheCatalogueAndTheCartAreNeverExemptedByDefault(): void
    {
        $container = new ContainerBuilder();
        (new TmonierSyliusChannelAccessExtension())->load([], $container);

        /** @var list<string> $routes */
        $routes = $container->getParameter('tmonier_channel_access.exempt_routes');
        foreach ($routes as $route) {
            self::assertStringNotContainsString('product', $route);
            self::assertStringNotContainsString('cart', $route);
            self::assertStringNotContainsString('checkout', $route);
            self::assertStringNotContainsString('taxon', $route);
        }
    }

    public function testTheServicesOfTheContractAreDefined(): void
    {
        $container = new ContainerBuilder();
        (new TmonierSyliusChannelAccessExtension())->load([], $container);

        foreach (['tmonier_channel_access.resolver', 'tmonier_channel_access.listener.visitor_access', 'tmonier_channel_access.modifier.order', 'tmonier_channel_access.normalizer.price_hiding'] as $id) {
            self::assertTrue($container->hasDefinition($id), $id);
        }
        self::assertSame('sylius.modifier.order', $container->getDefinition('tmonier_channel_access.modifier.order')->getDecoratedService()[0] ?? null);
    }
}
