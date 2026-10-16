<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;

/** US-042: the routes and paths of `restricted_routes` / `restricted_paths` are refused like the cart. */
final class ConfiguredRestrictionsTest extends WebTestCase
{
    use FunctionalTestTrait;

    protected function setUp(): void
    {
        $this->startScenario();
        $this->setMode(AccessMode::PricesHidden);
    }

    public function testAConfiguredRouteIsRefusedLikeTheCart(): void
    {
        $this->get('/restricted/exact');

        $this->assertRedirectedToLogin();
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
    }

    public function testAConfiguredPatternIsRefused(): void
    {
        $this->get('/restricted/wild-one');

        $this->assertRedirectedToLogin();
    }

    public function testAConfiguredApiPathAnswers401(): void
    {
        $this->client->request('GET', '/api/v2/shop/test-prices/ABC', [], [], $this->apiHeaders());

        self::assertSame(401, $this->statusCode());
        self::assertStringNotContainsString('feed-content', (string) $this->client->getResponse()->getContent());
    }

    public function testARouteThatIsNotConfiguredIsNotAffected(): void
    {
        $this->get('/unrestricted/page');

        self::assertSame(200, $this->statusCode());
    }

    public function testASignedInCustomerReachesTheRestrictedRoutes(): void
    {
        $this->loginCustomer();

        foreach (['/restricted/exact', '/restricted/wild-one'] as $path) {
            $this->get($path);
            self::assertSame(200, $this->statusCode(), $path);
        }
        $this->client->request('GET', '/api/v2/shop/test-prices/ABC', [], [], $this->apiHeaders(true));
        self::assertSame(200, $this->statusCode());
    }

    public function testAnOpenChannelRestrictsNobody(): void
    {
        $this->setMode(AccessMode::Open);

        foreach (['/restricted/exact', '/restricted/wild-one'] as $path) {
            $this->get($path);
            self::assertSame(200, $this->statusCode(), $path);
        }
    }

    public function testTheBuiltInRestrictionsStillApply(): void
    {
        $this->get($this->shopUrl('/cart/'));

        $this->assertRedirectedToLogin();
    }
}
