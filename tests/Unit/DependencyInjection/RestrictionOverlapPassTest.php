<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tmonier\SyliusChannelAccessPlugin\DependencyInjection\Compiler\RestrictionOverlapPass;
use Tmonier\SyliusChannelAccessPlugin\DependencyInjection\TmonierSyliusChannelAccessExtension;

/** A restriction and an exemption must not overlap: checked on the resolved values, entries named. */
final class RestrictionOverlapPassTest extends TestCase
{
    /** @return iterable<string, array{array<string, mixed>, list<string>}> */
    public static function overlaps(): iterable
    {
        yield 'path: the exemption covers a built-in restriction after resolution' => [['exempt_paths' => ['/api/v2/shop/*']], ['"/api/v2/shop/*"', '"/api/v2/shop/orders"']];
        yield 'path: the exemption covers a configured restriction' => [['exempt_paths' => ['/feeds/*'], 'restricted_paths' => ['/feeds/private/*']], ['"/feeds/*"', '"/feeds/private/*"']];
        yield 'path: the same pattern' => [['exempt_paths' => ['/feeds/*'], 'restricted_paths' => ['/feeds/*']], ['"/feeds/*"']];
        yield 'path: a restriction covering an exemption makes it dead' => [['exempt_paths' => ['/feeds/public'], 'restricted_paths' => ['/feeds/*']], ['"/feeds/public"', '"/feeds/*"']];
        yield 'route: the exemption covers a restricted route' => [['exempt_routes' => ['app_quote_*'], 'restricted_routes' => ['app_quote_new']], ['"app_quote_*"', '"app_quote_new"']];
        yield 'route: the exemption covers a built-in restriction' => [['exempt_routes' => ['sylius_shop_cart_*']], ['"sylius_shop_cart_*"']];
        yield 'route: a restricted pattern covers an exempt route' => [['exempt_routes' => ['app_feed_public'], 'restricted_routes' => ['app_feed_*']], ['"app_feed_public"', '"app_feed_*"']];
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string>         $named
     */
    #[DataProvider('overlaps')]
    public function testAnOverlapFailsTheCompilationAndNamesBothEntries(array $config, array $named): void
    {
        $container = $this->container($config);

        try {
            (new RestrictionOverlapPass())->process($container);
            self::fail('The overlap must be refused.');
        } catch (InvalidConfigurationException $exception) {
            foreach ($named as $entry) {
                self::assertStringContainsString($entry, $exception->getMessage());
            }
        }
    }

    public function testTheBuiltInAndSeparateListsDoNotOverlap(): void
    {
        $container = $this->container(['exempt_paths' => ['/feeds/*', '/punchout/*'], 'restricted_paths' => ['/api/v2/shop/product-variants/*/tier-prices'], 'exempt_routes' => ['app_mcp_*'], 'restricted_routes' => ['app_quote_*']]);

        (new RestrictionOverlapPass())->process($container);

        $this->addToAssertionCount(1);
    }

    public function testItIsHarmlessWithoutThePlugin(): void
    {
        (new RestrictionOverlapPass())->process(new ContainerBuilder());

        $this->addToAssertionCount(1);
    }

    /** @param array<string, mixed> $config */
    private function container(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('sylius.security.api_shop_route', '/api/v2/shop');
        $container->setParameter('sylius.security.api_admin_route', '/api/v2/admin');
        $container->setParameter('sylius_admin.path_name', 'admin');
        (new TmonierSyliusChannelAccessExtension())->load([$config], $container);

        return $container;
    }
}
