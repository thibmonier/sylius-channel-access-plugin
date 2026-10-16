<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin\Unit\Api;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ProductVariant;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;
use Tmonier\SyliusChannelAccessPlugin\Api\PriceHidingNormalizer;

final class PriceHidingNormalizerTest extends TestCase
{
    public function testItOnlyHandlesVariantsForAVisitorWhoMaySeeNoPrice(): void
    {
        self::assertTrue($this->normalizer(false)->supportsNormalization(new ProductVariant()));
        self::assertFalse($this->normalizer(true)->supportsNormalization(new ProductVariant()));
        self::assertFalse($this->normalizer(false)->supportsNormalization(new \stdClass()));
    }

    public function testItNeverHandlesItsOwnCall(): void
    {
        self::assertFalse($this->normalizer(false)->supportsNormalization(new ProductVariant(), null, ['tmonier_channel_access_price_hiding_already_called' => true]));
    }

    public function testItStripsThePricesTheSyliusNormalizerAdds(): void
    {
        $inner = $this->createMock(NormalizerInterface::class);
        $inner->method('normalize')->willReturn([
            'code' => 'V1',
            'inStock' => true,
            'price' => 1000,
            'originalPrice' => 1200,
            'lowestPriceBeforeDiscount' => 900,
            'appliedPromotions' => ['/api/v2/shop/catalog-promotions/x'],
        ]);
        $normalizer = $this->normalizer(false);
        $normalizer->setNormalizer($inner);

        self::assertSame(['code' => 'V1', 'inStock' => true], $normalizer->normalize(new ProductVariant()));
    }

    private function normalizer(bool $canSeePrices): PriceHidingNormalizer
    {
        $resolver = $this->createStub(AccessModeResolverInterface::class);
        $resolver->method('canSeePrices')->willReturn($canSeePrices);

        return new PriceHidingNormalizer($resolver);
    }
}
