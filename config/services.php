<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolver;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;
use Tmonier\SyliusChannelAccessPlugin\Access\PatternRequestMatcher;
use Tmonier\SyliusChannelAccessPlugin\Api\PriceHidingNormalizer;
use Tmonier\SyliusChannelAccessPlugin\EventListener\ClosedChannelExceptionListener;
use Tmonier\SyliusChannelAccessPlugin\EventListener\PrivateCacheListener;
use Tmonier\SyliusChannelAccessPlugin\EventListener\VisitorAccessListener;
use Tmonier\SyliusChannelAccessPlugin\Form\Extension\ChannelTypeExtension;
use Tmonier\SyliusChannelAccessPlugin\Order\PriceGuardOrderModifier;
use Tmonier\SyliusChannelAccessPlugin\Security\SeePricesVoter;
use Tmonier\SyliusChannelAccessPlugin\Twig\AccessExtension;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('tmonier_channel_access.resolver', AccessModeResolver::class)
        ->args([
            service('sylius.context.channel'),
            service('security.token_storage'),
            service('request_stack'),
            service('logger')->nullOnInvalid(),
        ])
    ;
    $services->alias(AccessModeResolverInterface::class, 'tmonier_channel_access.resolver');

    $services->set('tmonier_channel_access.matcher.exempt', PatternRequestMatcher::class)
        ->args(['%tmonier_channel_access.exempt_routes%', '%tmonier_channel_access.exempt_paths%'])
    ;
    $services->set('tmonier_channel_access.matcher.restricted_when_prices_hidden', PatternRequestMatcher::class)
        ->args(['%tmonier_channel_access.restricted_routes%', '%tmonier_channel_access.restricted_paths%'])
    ;

    // After the firewall (priority 8): the token is known.
    $services->set('tmonier_channel_access.listener.visitor_access', VisitorAccessListener::class)
        ->args([
            service('tmonier_channel_access.resolver'),
            service('tmonier_channel_access.matcher.exempt'),
            service('tmonier_channel_access.matcher.restricted_when_prices_hidden'),
            service('sylius.context.channel'),
            service('router'),
            '%tmonier_channel_access.login_route%',
            '%sylius.security.api_shop_route%',
        ])
        ->tag('kernel.event_listener', ['event' => 'kernel.request', 'priority' => 6])
    ;

    // Before the error page of the shop is rendered.
    $services->set('tmonier_channel_access.listener.closed_channel_exception', ClosedChannelExceptionListener::class)
        ->args([service('tmonier_channel_access.listener.visitor_access')])
        ->tag('kernel.event_listener', ['event' => 'kernel.exception', 'priority' => 64])
    ;

    $services->set('tmonier_channel_access.listener.private_cache', PrivateCacheListener::class)
        ->args([service('tmonier_channel_access.resolver'), service('tmonier_channel_access.matcher.exempt')])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'priority' => -100])
    ;

    $services->set('tmonier_channel_access.modifier.order', PriceGuardOrderModifier::class)
        ->decorate('sylius.modifier.order')
        ->args([service('.inner'), service('tmonier_channel_access.resolver')])
    ;

    $services->set('tmonier_channel_access.voter.see_prices', SeePricesVoter::class)
        ->args([service('tmonier_channel_access.resolver')])
        ->tag('security.voter')
    ;

    // Runs before the Sylius product variant normalizer (priority 64) and strips what it adds.
    $services->set('tmonier_channel_access.normalizer.price_hiding', PriceHidingNormalizer::class)
        ->args([service('tmonier_channel_access.resolver')])
        ->tag('serializer.normalizer', ['priority' => 65])
    ;

    $services->set('tmonier_channel_access.form.extension.channel', ChannelTypeExtension::class)
        ->tag('form.type_extension')
    ;

    $services->set('tmonier_channel_access.twig.extension', AccessExtension::class)
        ->args([service('tmonier_channel_access.resolver')])
        ->tag('twig.extension')
    ;
};
