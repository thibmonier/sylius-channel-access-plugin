<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\EventListener;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;
use Tmonier\SyliusChannelAccessPlugin\Access\PatternRequestMatcher;

/**
 * Closed channel: an anonymous visitor reaches the exempt routes only, every other request is
 * refused (login redirect for pages, 401 for the API). Prices hidden channel: an anonymous visitor
 * cannot reach the cart, the checkout and the order routes (restricted routes).
 */
final readonly class VisitorAccessListener
{
    public function __construct(
        private AccessModeResolverInterface $resolver,
        private PatternRequestMatcher $exempt,
        private PatternRequestMatcher $restrictedWhenPricesHidden,
        private ChannelContextInterface $channelContext,
        private UrlGeneratorInterface $urlGenerator,
        private string $loginRoute,
        private string $apiPathPrefix,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $this->refusalFor($event->getRequest());
        if ($response instanceof \Symfony\Component\HttpFoundation\Response) {
            $event->setResponse($response);
        }
    }

    /** The refusal to send to this request, or null when the visitor may go on. */
    public function refusalFor(Request $request): ?Response
    {
        $mode = $this->resolver->currentMode();
        if (AccessMode::Open === $mode || $this->resolver->isVisitorSignedIn()) {
            return null;
        }

        // A restriction wins over an exemption (deny over allow): the compilation refuses the overlaps it can see,
        // this settles the others, whatever the mode.
        if ($this->restrictedWhenPricesHidden->matches($request)) {
            return $this->refusal($request);
        }

        if ($this->exempt->matches($request)) {
            return null;
        }

        return AccessMode::Closed === $mode ? $this->refusal($request) : null;
    }

    private function refusal(Request $request): Response
    {
        if (str_starts_with($request->getPathInfo(), $this->apiPathPrefix . '/')) {
            return new JsonResponse(['code' => 401, 'message' => 'Authentication required.'], Response::HTTP_UNAUTHORIZED, ['Cache-Control' => 'private, no-store']);
        }

        // No target path is sent along: the redirect is always to the login page of the site.
        $response = new RedirectResponse($this->loginUrl($request), Response::HTTP_FOUND);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function loginUrl(Request $request): string
    {
        try {
            return $this->urlGenerator->generate($this->loginRoute, ['_locale' => $this->locale($request)]);
        } catch (RoutingException) {
            return $this->urlGenerator->generate($this->loginRoute, ['_locale' => $this->defaultLocale()]);
        }
    }

    private function locale(Request $request): string
    {
        $locale = $request->attributes->get('_locale');

        return \is_string($locale) && \in_array($locale, $this->channelLocaleCodes(), true) ? $locale : $this->defaultLocale();
    }

    private function defaultLocale(): string
    {
        $channel = $this->channel();

        return $channel?->getDefaultLocale()?->getCode() ?? 'en_US';
    }

    /** @return list<string> */
    private function channelLocaleCodes(): array
    {
        $codes = [];
        foreach ($this->channel()?->getLocales() ?? [] as $locale) {
            $codes[] = (string) $locale->getCode();
        }

        return $codes;
    }

    private function channel(): ?ChannelInterface
    {
        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return null;
        }

        return $channel instanceof ChannelInterface ? $channel : null;
    }
}
