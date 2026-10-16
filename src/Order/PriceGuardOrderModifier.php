<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\Order;

use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Model\OrderItemInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;

/**
 * Last line of defence behind the page guards: every way of adding an item to a cart (shop
 * pages, live components, shop API, re-order) goes through the order modifier, so an anonymous
 * visitor of a channel that hides prices cannot buy whatever route reaches it.
 */
final readonly class PriceGuardOrderModifier implements OrderModifierInterface
{
    public function __construct(
        private OrderModifierInterface $inner,
        private AccessModeResolverInterface $resolver,
    ) {
    }

    public function addToOrder(OrderInterface $order, OrderItemInterface $item): void
    {
        if (!$this->resolver->canSeePrices()) {
            throw new AccessDeniedException('Sign in to buy on this channel.');
        }

        $this->inner->addToOrder($order, $item);
    }

    public function removeFromOrder(OrderInterface $order, OrderItemInterface $item): void
    {
        $this->inner->removeFromOrder($order, $item);
    }
}
