<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Tests\Tmonier\SyliusChannelAccessPlugin\Entity\Channel\Channel;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;

final class AccessModeTraitTest extends TestCase
{
    public function testAChannelIsOpenByDefault(): void
    {
        self::assertSame(AccessMode::Open, (new Channel())->getAccessMode());
    }

    public function testTheModeIsKept(): void
    {
        $channel = new Channel();
        $channel->setAccessMode(AccessMode::PricesHidden);

        self::assertSame(AccessMode::PricesHidden, $channel->getAccessMode());
    }

    public function testAStoredValueThisVersionDoesNotKnowIsReadAsClosed(): void
    {
        $channel = new Channel();
        (new \ReflectionProperty($channel, 'accessMode'))->setValue($channel, 'invite_only');

        self::assertSame(AccessMode::Closed, $channel->getAccessMode());
    }

    public function testTheStoredValuesAreThePublicContract(): void
    {
        self::assertSame(['open', 'prices_hidden', 'closed'], array_map(static fn (AccessMode $mode): string => $mode->value, AccessMode::cases()));
    }
}
