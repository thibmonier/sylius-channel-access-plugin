<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\Access;

use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\User\Model\UserInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Tmonier\SyliusChannelAccessPlugin\Entity\Channel\AccessModeAwareInterface;

final class AccessModeResolver implements AccessModeResolverInterface
{
    private bool $misconfigurationLogged = false;

    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function modeOf(ChannelInterface $channel): AccessMode
    {
        if (!$channel instanceof AccessModeAwareInterface) {
            // The application forgot AccessModeTrait: refuse rather than silently open everything.
            if (!$this->misconfigurationLogged) {
                $this->misconfigurationLogged = true;
                $this->logger?->error('The channel class "{class}" does not implement AccessModeAwareInterface: the channel is treated as closed.', ['class' => $channel::class]);
            }

            return AccessMode::Closed;
        }

        return $channel->getAccessMode();
    }

    public function currentMode(): AccessMode
    {
        // Console commands and message handlers have no visitor (and a single-channel shop would
        // resolve its channel for them anyway).
        if (!$this->requestStack->getMainRequest() instanceof \Symfony\Component\HttpFoundation\Request) {
            return AccessMode::Open;
        }

        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            // An HTTP request that no channel claims: refuse rather than serve it unprotected.
            return AccessMode::Closed;
        }

        return $channel instanceof ChannelInterface ? $this->modeOf($channel) : AccessMode::Closed;
    }

    public function isVisitorSignedIn(): bool
    {
        return $this->tokenStorage->getToken()?->getUser() instanceof UserInterface;
    }

    public function canSeeCatalog(): bool
    {
        return AccessMode::Closed !== $this->currentMode() || $this->isVisitorSignedIn();
    }

    public function canSeePrices(): bool
    {
        return AccessMode::Open === $this->currentMode() || $this->isVisitorSignedIn();
    }
}
