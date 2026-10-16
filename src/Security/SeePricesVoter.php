<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;

/**
 * Lets the Twig hook conditions (`@=is_granted('TMONIER_CHANNEL_ACCESS_SEE_PRICES')`) ask the
 * resolver. The decision does not depend on the token passed in: the resolver reads the current
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
        return ChannelAccessPermissions::SEE_PRICES === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        return $this->resolver->canSeePrices();
    }
}
