<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;

/**
 * Lets the Twig hook conditions (`@=is_granted('TMONIER_CHANNEL_ACCESS_SEE_PRICES')`, `..._SEE_CATALOG`)
 * ask the resolver. The decision does not depend on the token passed in: the resolver reads the current
 * one.
 *
 * @extends Voter<string, mixed>
 */
final class SeePricesVoter extends Voter
{
    public function __construct(private readonly AccessModeResolverInterface $resolver)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [ChannelAccessPermissions::SEE_PRICES, ChannelAccessPermissions::SEE_CATALOG], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return ChannelAccessPermissions::SEE_CATALOG === $attribute ? $this->resolver->canSeeCatalog() : $this->resolver->canSeePrices();
    }
}
