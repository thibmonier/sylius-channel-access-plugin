<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\Twig;

use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AccessExtension extends AbstractExtension
{
    public function __construct(private readonly AccessModeResolverInterface $resolver)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tmonier_channel_access_mode', fn (): string => $this->resolver->currentMode()->value),
            new TwigFunction('tmonier_channel_can_see_prices', $this->resolver->canSeePrices(...)),
            new TwigFunction('tmonier_channel_can_see_catalog', $this->resolver->canSeeCatalog(...)),
        ];
    }
}
