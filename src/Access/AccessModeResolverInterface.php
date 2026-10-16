<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\Access;

use Sylius\Component\Core\Model\ChannelInterface;

/**
 * Public contract of the plugin (frozen for 0.x): other plugins ask it what the current visitor
 * may see instead of reading the channel themselves. The visitor is "signed in" when the security
 * token holds a Sylius user (shop customer or administrator); everything else is anonymous.
 *
 * Without an HTTP request (console command, message handler) or without a resolvable channel the
 * mode is `open`: the restrictions protect HTTP visitors.
 */
interface AccessModeResolverInterface
{
    /** The mode stored on the channel; an unknown stored value is read as `closed` (deny by default). */
    public function modeOf(ChannelInterface $channel): AccessMode;

    /** The mode of the channel of the current request. */
    public function currentMode(): AccessMode;

    public function isVisitorSignedIn(): bool;

    /** False for an anonymous visitor on a closed channel (exempt routes aside). */
    public function canSeeCatalog(): bool;

    /** False for an anonymous visitor on a channel that is not open. */
    public function canSeePrices(): bool;
}
