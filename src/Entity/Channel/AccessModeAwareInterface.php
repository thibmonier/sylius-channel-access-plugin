<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\Entity\Channel;

use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;

/** Implemented by the channel entity of the application (see AccessModeTrait). */
interface AccessModeAwareInterface
{
    public function getAccessMode(): AccessMode;

    public function setAccessMode(AccessMode $accessMode): void;
}
