<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\Security;

final class ChannelAccessPermissions
{
    /** `is_granted()` attribute: the current visitor may see prices (used by the Twig hooks). */
    public const SEE_PRICES = 'TMONIER_CHANNEL_ACCESS_SEE_PRICES';

    private function __construct()
    {
    }
}
