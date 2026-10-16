<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\EventListener;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;
use Tmonier\SyliusChannelAccessPlugin\Access\PatternRequestMatcher;

/**
 * A page that differs between an anonymous and a signed-in visitor must not be served from a
 * shared cache: the response of a restricted channel is private and not stored. An exempt route
 * that sets its response public on purpose (feeds, PunchOut...) keeps its own caching headers.
 */
final readonly class PrivateCacheListener
{
    public function __construct(
        private AccessModeResolverInterface $resolver,
        private PatternRequestMatcher $exempt,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        if (!$event->isMainRequest() || $response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return;
        }

        if (AccessMode::Open === $this->resolver->currentMode()) {
            return;
        }

        // An exempt route (feed, PunchOut...) may be public on purpose; the built-in pages
        // (home, login) are not: they differ between visitors.
        if ($response->headers->hasCacheControlDirective('public') && $this->exempt->matches($event->getRequest())) {
            return;
        }

        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Vary', 'Cookie, Authorization', false);
    }
}
