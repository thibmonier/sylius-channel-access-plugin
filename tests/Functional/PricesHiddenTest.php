<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin\Functional;

use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;

final class PricesHiddenTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const BUY_BUTTON = 'add_to_cart_form';

    protected function setUp(): void
    {
        $this->startScenario();
        $this->setMode(AccessMode::PricesHidden);
    }

    public function testTheProductPageReplacesThePriceAndTheBuyButtonByTheLoginLink(): void
    {
        $html = $this->get($this->productUrl());

        self::assertSame(200, $this->statusCode());
        self::assertDoesNotMatchRegularExpression($this->pricePattern(), $html);
        self::assertStringNotContainsString(self::BUY_BUTTON, $html);
        self::assertStringContainsString('data-test-tmonier-prices-hidden', $html);
        self::assertStringContainsString('Log in to see your prices', $html, substr($html, (int) strpos($html, 'data-test-tmonier-prices-hidden') - 50, 300));
        self::assertStringContainsString('href="' . $this->shopUrl('/login') . '"', $html);
        // The product itself stays visible.
        self::assertStringContainsString((string) $this->product()->getName(), $html);
    }

    public function testListingsAndHomepageShowNoPrice(): void
    {
        $taxon = $this->product()->getMainTaxon();
        self::assertNotNull($taxon);

        $this->assertNoPriceIn([
            $this->shopUrl('/'),
            $this->shopUrl('/taxons/' . $taxon->getTranslation($this->localeCode())->getSlug()),
        ]);
    }

    public function testASignedInCustomerSeesPricesAndBuyButtonAsBeforeThePlugin(): void
    {
        $this->loginCustomer();

        $html = $this->get($this->productUrl());

        self::assertMatchesRegularExpression($this->pricePattern(), $html);
        self::assertStringContainsString(self::BUY_BUTTON, $html);
        self::assertStringNotContainsString('data-test-tmonier-prices-hidden', $html);
    }

    public function testAnOpenChannelIsRenderedAsBeforeThePlugin(): void
    {
        $this->setMode(AccessMode::Open);

        $html = $this->get($this->productUrl());

        self::assertMatchesRegularExpression($this->pricePattern(), $html);
        self::assertStringContainsString(self::BUY_BUTTON, $html);
        self::assertStringNotContainsString('data-test-tmonier-prices-hidden', $html);
    }

    public function testTheCartAndTheCheckoutSendAnAnonymousVisitorToTheLogin(): void
    {
        foreach (['/cart/', '/checkout/address'] as $path) {
            $this->get($this->shopUrl($path));
            $this->assertRedirectedToLogin($path);
        }
    }

    public function testResponsesArePrivateBecauseTheyDifferBetweenVisitors(): void
    {
        $this->get($this->productUrl());

        $headers = $this->client->getResponse()->headers;
        self::assertTrue($headers->hasCacheControlDirective('private'));
        self::assertTrue($headers->hasCacheControlDirective('no-store'));
    }

    public function testTheShopApiDoesNotReturnAnyPriceToAnAnonymousVisitor(): void
    {
        $variant = $this->variant();
        $variantUrl = '/api/v2/shop/product-variants/' . $variant->getCode();

        // Control: an open channel returns the price.
        $this->setMode(AccessMode::Open);
        $this->client->request('GET', $variantUrl, [], [], $this->apiHeaders());
        self::assertSame(200, $this->statusCode());
        self::assertArrayHasKey('price', $this->json());

        $this->setMode(AccessMode::PricesHidden);
        foreach ([$variantUrl, '/api/v2/shop/products/' . $this->product()->getCode(), '/api/v2/shop/products'] as $url) {
            $this->client->request('GET', $url, [], [], $this->apiHeaders());
            self::assertSame(200, $this->statusCode(), $url);
            $data = $this->json();
            unset($data['hydra:search']); // filter descriptions name "price", not values
            $leaks = array_intersect(['price', 'originalPrice', 'lowestPriceBeforeDiscount', 'appliedPromotions'], $this->keysOf($data));
            self::assertSame([], array_values($leaks), $url);
        }
        // The rest of the variant is unchanged.
        $this->client->request('GET', $variantUrl, [], [], $this->apiHeaders());
        self::assertSame($variant->getCode(), $this->json()['code']);
        self::assertArrayHasKey('inStock', $this->json());
    }

    public function testTheShopApiReturnsPricesToASignedInCustomer(): void
    {
        $this->client->request('GET', '/api/v2/shop/product-variants/' . $this->variant()->getCode(), [], [], $this->apiHeaders(true));

        self::assertSame(200, $this->statusCode());
        self::assertArrayHasKey('price', $this->json());
    }

    public function testAnAnonymousVisitorCannotCreateOrFillACartThroughTheApi(): void
    {
        $this->client->request('POST', '/api/v2/shop/orders', [], [], $this->apiHeaders(), '{"localeCode":"' . $this->localeCode() . '"}');
        self::assertSame(401, $this->statusCode());

        // A cart opened before the channel hid its prices cannot be filled any more.
        $this->setMode(AccessMode::Open);
        $this->client->request('POST', '/api/v2/shop/orders', [], [], $this->apiHeaders(), '{"localeCode":"' . $this->localeCode() . '"}');
        self::assertSame(201, $this->statusCode());
        $token = $this->stringField('tokenValue');

        $this->setMode(AccessMode::PricesHidden);
        $this->client->request('POST', '/api/v2/shop/orders/' . $token . '/items', [], [], $this->apiHeaders(), (string) json_encode(['productVariant' => '/api/v2/shop/product-variants/' . $this->variant()->getCode(), 'quantity' => 1]));
        self::assertContains($this->statusCode(), [401, 403]);

        $this->client->request('GET', '/api/v2/shop/orders/' . $token, [], [], $this->apiHeaders());
        self::assertContains($this->statusCode(), [401, 403]);
    }

    public function testASignedInCustomerBuysAsBeforeThePlugin(): void
    {
        $this->client->request('POST', '/api/v2/shop/orders', [], [], $this->apiHeaders(true), '{"localeCode":"' . $this->localeCode() . '"}');
        self::assertSame(201, $this->statusCode());
        $token = $this->stringField('tokenValue');

        $this->client->request('POST', '/api/v2/shop/orders/' . $token . '/items', [], [], $this->apiHeaders(true), (string) json_encode(['productVariant' => '/api/v2/shop/product-variants/' . $this->variant()->getCode(), 'quantity' => 1]));

        self::assertResponseStatusCodeSame(201);
        $items = $this->json()['items'] ?? null;
        self::assertIsArray($items);
        self::assertCount(1, $items);
    }

    private function variant(): ProductVariantInterface
    {
        $variant = $this->product()->getEnabledVariants()->first();
        self::assertInstanceOf(ProductVariantInterface::class, $variant);

        return $variant;
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    private function keysOf(array $data): array
    {
        $keys = [];
        foreach ($data as $key => $value) {
            $keys[] = (string) $key;
            if (\is_array($value)) {
                array_push($keys, ...$this->keysOf($value));
            }
        }

        return $keys;
    }

    private function stringField(string $name): string
    {
        $value = $this->json()[$name] ?? null;
        self::assertIsString($value);

        return $value;
    }

    /** @return array<mixed> */
    private function json(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data, (string) $this->client->getResponse()->getContent());

        return $data;
    }
}
