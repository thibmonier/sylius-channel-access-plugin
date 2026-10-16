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

    /** @return iterable<string, array{string}> */
    public static function variantsOfARestrictedApiPath(): iterable
    {
        yield 'trailing slash' => ['/api/v2/shop/orders/'];
        yield 'json format suffix' => ['/api/v2/shop/orders.json'];
        yield 'jsonld format suffix' => ['/api/v2/shop/orders.jsonld'];
        yield 'item' => ['/api/v2/shop/orders/ABC'];
        yield 'item with suffix' => ['/api/v2/shop/orders/ABC.jsonld'];
        yield 'configured path with suffix' => ['/api/v2/shop/test-prices/ABC.json'];
        yield 'configured path, trailing slash' => ['/api/v2/shop/test-prices/ABC/'];
        yield 'configured path, code with two dots' => ['/api/v2/shop/test-prices/A..B'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('variantsOfARestrictedApiPath')]
    public function testTheVariantsOfARestrictedApiPathAreRefusedToo(string $path): void
    {
        $this->client->request('GET', $path, [], [], $this->apiHeaders());

        self::assertSame(401, $this->statusCode(), $path);
    }

    /** @return iterable<string, array{string}> */
    public static function variantsOfARestrictedPage(): iterable
    {
        yield 'without the trailing slash' => ['/cart'];
        yield 'with the trailing slash' => ['/cart/'];
        yield 'checkout step' => ['/checkout/address'];
        yield 'query string' => ['/cart/?x=1'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('variantsOfARestrictedPage')]
    public function testTheVariantsOfARestrictedPageEndOnTheLoginForAnAnonymousVisitor(string $path): void
    {
        $this->client->followRedirects();
        $this->get($this->shopUrl($path));

        self::assertSame(200, $this->statusCode(), $path);
        self::assertSame($this->shopUrl('/login'), $this->client->getRequest()->getPathInfo(), $path);
    }
}
