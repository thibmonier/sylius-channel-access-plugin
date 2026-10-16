<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;

final class ClosedChannelTest extends WebTestCase
{
    use FunctionalTestTrait;

    protected function setUp(): void
    {
        $this->startScenario();
        $this->setMode(AccessMode::Closed);
    }

    public function testAnonymousVisitorIsSentToTheLoginFromTheCatalogue(): void
    {
        $html = $this->get($this->productUrl());

        $this->assertRedirectedToLogin();
        self::assertStringNotContainsString($this->product()->getName() ?? 'unreachable', $html, 'no catalogue content in the redirect');
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('private'));
    }

    #[DataProvider('catalogueAndPrivatePaths')]
    public function testEveryPathOutsideTheAllowListIsRefused(string $path): void
    {
        $this->get($path);

        $this->assertRedirectedToLogin($path);
    }

    /** @return iterable<string, array{string}> */
    public static function catalogueAndPrivatePaths(): iterable
    {
        yield 'taxon listing' => ['/en_US/taxons/t-shirts'];
        yield 'product listing' => ['/en_US/products/anything'];
        yield 'cart' => ['/en_US/cart/'];
        yield 'checkout' => ['/en_US/checkout/address'];
        yield 'contact' => ['/en_US/contact/'];
        yield 'live component' => ['/_components/sylius_shop:product:add_to_cart_form/get'];
        yield 'unknown path' => ['/en_US/does-not-exist'];
        yield 'route outside the allow list' => ['/private/page'];
        yield 'path climbing out of an exempt prefix' => ['/feeds/../private/page'];
        yield 'doubled slash' => ['/feeds//../private/page'];
    }

    public function testBareRootAndHomepageStayReachable(): void
    {
        $this->get('/');
        self::assertSame(302, $this->statusCode());
        self::assertSame($this->shopUrl('/login'), $this->location(), 'the bare root goes through the login of a closed channel');

        $this->get($this->shopUrl('/'));
        self::assertSame(200, $this->statusCode());
    }

    #[DataProvider('exemptPaths')]
    public function testExemptRoutesAnswerWithoutSigningIn(string $path): void
    {
        $this->get($path);

        self::assertSame(200, $this->statusCode(), $path);
    }

    /** @return iterable<string, array{string}> */
    public static function exemptPaths(): iterable
    {
        yield 'home' => ['/en_US/'];
        yield 'login' => ['/en_US/login'];
        yield 'registration' => ['/en_US/register'];
        yield 'password reset request' => ['/en_US/forgotten-password'];
        yield 'configured path (feed)' => ['/feeds/products.txt'];
        yield 'configured route (integration endpoint)' => ['/integrations/setup'];
    }

    public function testThePricesAreHiddenOnTheExemptHomepage(): void
    {
        $html = $this->get($this->shopUrl('/'));

        self::assertDoesNotMatchRegularExpression($this->pricePattern(), $html);
        self::assertStringContainsString('data-test-tmonier-prices-hidden', $html);
    }

    public function testASignedInCustomerBrowsesTheCatalogueAsBeforeThePlugin(): void
    {
        $this->loginCustomer();

        $html = $this->get($this->productUrl());

        self::assertSame(200, $this->statusCode());
        self::assertMatchesRegularExpression($this->pricePattern(), $html);
        self::assertStringNotContainsString('data-test-tmonier-prices-hidden', $html);
        $this->get($this->shopUrl('/cart/'));
        self::assertSame(200, $this->statusCode());
    }

    public function testTheLoginRedirectNeverFollowsAnInputOfTheRequest(): void
    {
        foreach ([
            $this->productUrl() . '?_target_path=https://evil.example/&redirect=//evil.example',
            '/%2e%2e/en_US/products/x',
        ] as $url) {
            $this->client->request('GET', $url, [], [], ['HTTP_HOST' => 'localhost', 'HTTP_REFERER' => 'https://evil.example/', 'HTTP_X_FORWARDED_HOST' => 'evil.example']);

            self::assertSame(302, $this->statusCode(), $url);
            self::assertSame($this->shopUrl('/login'), $this->location(), $url);
        }
    }

    public function testALocaleTheChannelDoesNotOfferNeverReachesTheLoginUrl(): void
    {
        $this->get('/xx_YY/products/x');

        self::assertSame(302, $this->statusCode());
        self::assertStringStartsWith('/' . $this->localeCode() . '/', $this->location());
    }

    public function testTheLoginLocaleIsTheRequestOneWhenTheChannelOffersIt(): void
    {
        $other = $this->offerLocale('fr_FR');

        $this->get('/' . $other . '/products/x');

        self::assertSame('/' . $other . '/login', $this->location());
    }

    public function testTheApiRefusesAnAnonymousVisitorExceptWhereCustomersSignInOrRegister(): void
    {
        foreach (['/api/v2/shop/products', '/api/v2/shop/product-variants', '/api/v2/shop/taxons', '/api/v2/shop/channels', '/api/v2/shop/orders'] as $url) {
            $this->client->request('GET', $url, [], [], $this->apiHeaders());
            self::assertSame(401, $this->statusCode(), $url);
            self::assertStringNotContainsString('hydra:member', (string) $this->client->getResponse()->getContent());
        }

        // Registration is reachable: validation answers, not the plugin.
        $this->client->request('POST', '/api/v2/shop/customers', [], [], $this->apiHeaders(), '{}');
        self::assertSame(422, $this->statusCode());
    }

    public function testTheApiServesASignedInCustomer(): void
    {
        $this->client->request('GET', '/api/v2/shop/products', [], [], $this->apiHeaders(true));

        self::assertSame(200, $this->statusCode());
    }

    public function testAdministrationStaysReachable(): void
    {
        $this->get('/admin/login');

        self::assertSame(200, $this->statusCode());
    }

    public function testResponsesOfAClosedChannelAreNotCacheable(): void
    {
        $this->get($this->shopUrl('/'));

        $control = (string) $this->client->getResponse()->headers->get('Cache-Control');
        self::assertStringContainsString('private', $control);
        self::assertStringContainsString('no-store', $control);
        self::assertStringContainsString('Cookie', (string) $this->client->getResponse()->headers->get('Vary'));
    }
}
