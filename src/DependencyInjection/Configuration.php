<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /** Patterns that would exempt every request: refused. */
    private const array TOO_BROAD = ['*', '**', '/*', '/**', '*/*'];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('tmonier_sylius_channel_access');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('login_route')
                    ->info('Route anonymous visitors are redirected to on a closed channel (must accept a "_locale" parameter).')
                    ->defaultValue('sylius_shop_login')
                    ->cannotBeEmpty()
                ->end()
                ->append($this->patterns('exempt_routes', 'Route names (shell-style patterns) an anonymous visitor reaches on a closed channel, in addition to the built-in ones (home, login, registration, password reset).'))
                ->append($this->patterns('exempt_paths', 'Paths (shell-style patterns starting with "/") an anonymous visitor reaches on a closed channel, in addition to the built-in ones (administration, assets, profiler). Example: PunchOut, MCP and feed endpoints.'))
                ->append($this->patterns('restricted_routes', 'Route names (shell-style patterns) refused to a visitor who may not see the prices (prices hidden channel), in addition to the built-in ones (cart, checkout, orders): redirection to the login, 401 for the shop API. Example: the quote request form. A route that is also in exempt_routes is a configuration error.'))
                ->append($this->patterns('restricted_paths', 'Paths (shell-style patterns, "%parameter%" placeholders allowed) refused like restricted_routes, in addition to the built-in ones (shop API orders). Example: "%sylius.security.api_shop_route%/product-variants/*/tier-prices".'))
            ->end()
        ;

        return $treeBuilder;
    }

    private function patterns(string $name, string $info): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition($name);
        $node
            ->info($info)
            ->scalarPrototype()->cannotBeEmpty()->end()
            ->defaultValue([])
            ->validate()
                ->ifTrue(static fn (array $patterns): bool => [] !== array_filter($patterns, static fn (mixed $pattern): bool => !\is_string($pattern)))
                ->thenInvalid('Every pattern must be a non-empty string: %s')
            ->end()
            ->validate()
                ->ifTrue(static fn (array $patterns): bool => [] !== array_filter($patterns, static fn (mixed $pattern): bool => \in_array($pattern, self::TOO_BROAD, true)))
                ->thenInvalid('A pattern that matches every request would open the closed channel: %s')
            ->end()
        ;

        return $node;
    }
}
