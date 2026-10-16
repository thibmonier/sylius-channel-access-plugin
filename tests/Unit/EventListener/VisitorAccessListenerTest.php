<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin\Unit\EventListener;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;
use Tmonier\SyliusChannelAccessPlugin\Access\PatternRequestMatcher;
use Tmonier\SyliusChannelAccessPlugin\EventListener\VisitorAccessListener;

final class VisitorAccessListenerTest extends TestCase
{
    /** The safest rule when both lists match (partial overlaps the compilation cannot see): the restriction wins. */
    public function testARestrictionWinsOverAnExemption(): void
    {
        foreach ([AccessMode::PricesHidden, AccessMode::Closed] as $mode) {
            $listener = $this->listener($mode, new PatternRequestMatcher([], ['/api/*/x']), new PatternRequestMatcher([], ['/api/v2/*']));

            self::assertNotNull($listener->refusalFor(Request::create('/api/v2/x')), $mode->value);
        }
    }

    public function testAnExemptionStillOpensAClosedChannel(): void
    {
        $listener = $this->listener(AccessMode::Closed, new PatternRequestMatcher([], ['/feeds/*']), new PatternRequestMatcher([], ['/api/*'], true));

        self::assertNull($listener->refusalFor(Request::create('/feeds/products.xml')));
        self::assertNotNull($listener->refusalFor(Request::create('/en_US/products/x')));
    }

    public function testAPricesHiddenChannelOnlyRefusesTheRestrictedRequests(): void
    {
        $listener = $this->listener(AccessMode::PricesHidden, new PatternRequestMatcher([], []), new PatternRequestMatcher([], ['/api/*'], true));

        self::assertNotNull($listener->refusalFor(Request::create('/api/v2/shop/orders')));
        self::assertNull($listener->refusalFor(Request::create('/en_US/products/x')));
    }

    private function listener(AccessMode $mode, PatternRequestMatcher $exempt, PatternRequestMatcher $restricted): VisitorAccessListener
    {
        $resolver = $this->createStub(AccessModeResolverInterface::class);
        $resolver->method('currentMode')->willReturn($mode);
        $resolver->method('isVisitorSignedIn')->willReturn(false);
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/en_US/login');

        return new VisitorAccessListener($resolver, $exempt, $restricted, $this->createStub(ChannelContextInterface::class), $urls, 'sylius_shop_login', '/api/v2/shop');
    }
}
