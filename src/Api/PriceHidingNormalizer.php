<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\Api;

use Sylius\Component\Core\Model\ProductVariantInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;

/**
 * Removes what the Sylius normalizer adds to a product variant of the shop API (price, original
 * price, lowest price before discount, applied catalog promotions) for a visitor who may not see
 * prices. Runs before the Sylius normalizer (higher priority) and strips its result.
 */
final class PriceHidingNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'tmonier_channel_access_price_hiding_already_called';

    private const HIDDEN_KEYS = ['price', 'originalPrice', 'lowestPriceBeforeDiscount', 'appliedPromotions'];

    public function __construct(private readonly AccessModeResolverInterface $resolver)
    {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return \ArrayObject<array-key, mixed>|array<mixed>|string|int|float|bool|null
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): \ArrayObject|array|string|int|float|bool|null
    {
        $context[self::ALREADY_CALLED] = true;
        $normalized = $this->normalizer->normalize($data, $format, $context);

        return \is_array($normalized) ? array_diff_key($normalized, array_flip(self::HIDDEN_KEYS)) : $normalized;
    }

    /** @param array<string, mixed> $context */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) &&
            $data instanceof ProductVariantInterface &&
            !$this->resolver->canSeePrices();
    }

    public function getSupportedTypes(?string $format): array
    {
        // Not cacheable: the answer depends on the visitor.
        return [ProductVariantInterface::class => false];
    }
}
