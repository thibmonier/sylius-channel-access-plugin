<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\Entity\Channel;

use Doctrine\ORM\Mapping as ORM;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;

/**
 * Adds the access mode to the channel entity of the application:
 *
 *     #[ORM\Entity]
 *     #[ORM\Table(name: 'sylius_channel')]
 *     class Channel extends BaseChannel implements AccessModeAwareInterface
 *     {
 *         use AccessModeTrait;
 *     }
 *
 * One additive column (`access_mode`, default `open`): existing channels stay open.
 */
trait AccessModeTrait
{
    #[ORM\Column(name: 'access_mode', type: 'string', length: 20, options: ['default' => 'open'])]
    protected string $accessMode = 'open';

    public function getAccessMode(): AccessMode
    {
        // A value this version does not know (manual edit, rollback to an older plugin) must not
        // open the channel.
        return AccessMode::tryFrom($this->accessMode) ?? AccessMode::Closed;
    }

    public function setAccessMode(AccessMode $accessMode): void
    {
        $this->accessMode = $accessMode->value;
    }
}
