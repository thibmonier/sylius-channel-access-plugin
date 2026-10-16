<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\DependencyInjection;

use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class TmonierSyliusChannelAccessExtension extends Extension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    /** Reachable by an anonymous visitor of a closed channel: the pages needed to sign in or register. */
    private const array BUILT_IN_EXEMPT_ROUTES = [
        'sylius_shop_homepage',
        'sylius_shop_switch_locale',
        'sylius_shop_switch_currency',
        'sylius_shop_login',
        'sylius_shop_login_check',
        'sylius_shop_json_login_check',
        'sylius_shop_logout',
        'sylius_shop_register',
        'sylius_shop_register_thank_you',
        'sylius_shop_resend_verification_email',
        'sylius_shop_request_password_reset_token',
        'sylius_shop_password_reset',
        'sylius_shop_user_request_verification_token',
        'sylius_shop_user_verification',
    ];

    /** Placeholders are resolved by the container. */
    private const array BUILT_IN_EXEMPT_PATHS = [
        '/%sylius_admin.path_name%',
        '/%sylius_admin.path_name%/*',
        '%sylius.security.api_admin_route%/*',
        '%sylius.security.api_shop_route%/customers/token',
        '%sylius.security.api_shop_route%/customers',
        '%sylius.security.api_shop_route%/customers/reset-password*',
        '%sylius.security.api_shop_route%/customers/verify*',
        '%sylius.security.api_shop_route%/customers/resend-verification-email*',
        '/_wdt/*',
        '/_profiler',
        '/_profiler/*',
        '/_error/*',
    ];

    /** Anonymous visitors of a prices hidden channel are sent to the login page for these. */
    private const array RESTRICTED_ROUTES = [
        'sylius_shop_cart_*',
        'sylius_shop_checkout_*',
        'sylius_shop_order_*',
        'sylius_shop_payment_request_*',
    ];

    private const array RESTRICTED_PATHS = [
        '%sylius.security.api_shop_route%/orders',
        '%sylius.security.api_shop_route%/orders/*',
        // Format suffixes of the API routes (orders.json, orders.jsonld).
        '%sylius.security.api_shop_route%/orders.*',
    ];

    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{login_route: string, exempt_routes: list<string>, exempt_paths: list<string>, restricted_routes: list<string>, restricted_paths: list<string>} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $exemptRoutes = array_values(array_unique([...self::BUILT_IN_EXEMPT_ROUTES, ...$config['exempt_routes']]));
        $exemptPaths = array_values(array_unique([...self::BUILT_IN_EXEMPT_PATHS, ...$config['exempt_paths']]));
        $restrictedRoutes = array_values(array_unique([...self::RESTRICTED_ROUTES, ...$config['restricted_routes']]));
        $restrictedPaths = array_values(array_unique([...self::RESTRICTED_PATHS, ...$config['restricted_paths']]));

        $container->setParameter('tmonier_channel_access.login_route', $config['login_route']);
        $container->setParameter('tmonier_channel_access.exempt_routes', $exemptRoutes);
        $container->setParameter('tmonier_channel_access.exempt_paths', $exemptPaths);
        $container->setParameter('tmonier_channel_access.restricted_routes', $restrictedRoutes);
        $container->setParameter('tmonier_channel_access.restricted_paths', $restrictedPaths);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');
    }

    public function prepend(ContainerBuilder $container): void
    {
        $this->prependDoctrineMigrations($container);
    }

    public function getAlias(): string
    {
        return 'tmonier_sylius_channel_access';
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Tmonier\SyliusChannelAccessPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@TmonierSyliusChannelAccessPlugin/src/Migrations';
    }

    /** @return list<string> */
    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }
}
