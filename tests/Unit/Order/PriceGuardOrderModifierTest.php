<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin\Unit\Order;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;
use Tmonier\SyliusChannelAccessPlugin\Order\PriceGuardOrderModifier;

final class PriceGuardOrderModifierTest extends TestCase
{
    public function testItDelegatesWhenThePricesAreVisible(): void
    {
        $inner = $this->createMock(OrderModifierInterface::class);
        $inner->expects(self::once())->method('addToOrder');

        (new PriceGuardOrderModifier($inner, $this->resolver(true)))->addToOrder(new Order(), new OrderItem());
    }

    public function testItRefusesAndDoesNotDelegateOtherwise(): void
    {
        $inner = $this->createMock(OrderModifierInterface::class);
        $inner->expects(self::never())->method('addToOrder');

        $this->expectException(AccessDeniedException::class);
        (new PriceGuardOrderModifier($inner, $this->resolver(false)))->addToOrder(new Order(), new OrderItem());
    }

    public function testRemovingFromACartIsNeverRefused(): void
    {
        $inner = $this->createMock(OrderModifierInterface::class);
        $inner->expects(self::once())->method('removeFromOrder');

        (new PriceGuardOrderModifier($inner, $this->resolver(false)))->removeFromOrder(new Order(), new OrderItem());
    }

    private function resolver(bool $canSeePrices): AccessModeResolverInterface
    {
        $resolver = $this->createStub(AccessModeResolverInterface::class);
        $resolver->method('canSeePrices')->willReturn($canSeePrices);

        return $resolver;
    }
}
