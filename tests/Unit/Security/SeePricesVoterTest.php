<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin\Unit\Security;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;
use Tmonier\SyliusChannelAccessPlugin\Security\ChannelAccessPermissions;
use Tmonier\SyliusChannelAccessPlugin\Security\SeePricesVoter;

final class SeePricesVoterTest extends TestCase
{
    public function testItFollowsTheResolver(): void
    {
        foreach ([[true, VoterInterface::ACCESS_GRANTED], [false, VoterInterface::ACCESS_DENIED]] as [$canSee, $expected]) {
            $resolver = $this->createStub(AccessModeResolverInterface::class);
            $resolver->method('canSeePrices')->willReturn($canSee);

            self::assertSame($expected, (new SeePricesVoter($resolver))->vote(new NullToken(), null, [ChannelAccessPermissions::SEE_PRICES]));
        }
    }

    public function testItAbstainsOnOtherAttributes(): void
    {
        $voter = new SeePricesVoter($this->createStub(AccessModeResolverInterface::class));

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new NullToken(), null, ['ROLE_USER']));
    }
}
