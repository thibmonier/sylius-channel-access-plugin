<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin\Integration;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tests\Tmonier\SyliusChannelAccessPlugin\ChannelAccessTestTrait;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolverInterface;

/**
 * Whatever page, live component or API route reaches the order modifier, an anonymous visitor of
 * a channel that hides prices cannot add an item to a cart.
 */
final class AddToCartGuardTest extends KernelTestCase
{
    use ChannelAccessTestTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->beginTestTransaction();
        $request = Request::create('http://localhost/en_US/');
        $request->attributes->set('_locale', 'en_US');
        $requestStack = self::getContainer()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->push($request);
    }

    protected function tearDown(): void
    {
        $this->rollBackTestTransaction();
        parent::tearDown();
    }

    public function testAnonymousVisitorCannotAddToACartWhenPricesAreHidden(): void
    {
        $this->setMode(AccessMode::PricesHidden);
        [$cart, $item] = $this->cartAndItem();

        $this->expectException(AccessDeniedException::class);
        $this->modifier()->addToOrder($cart, $item);
    }

    public function testAnonymousVisitorCannotAddToACartOnAClosedChannel(): void
    {
        $this->setMode(AccessMode::Closed);
        [$cart, $item] = $this->cartAndItem();

        $this->expectException(AccessDeniedException::class);
        $this->modifier()->addToOrder($cart, $item);
    }

    public function testAnonymousVisitorAddsToACartOnAnOpenChannel(): void
    {
        $this->setMode(AccessMode::Open);
        [$cart, $item] = $this->cartAndItem();

        $this->modifier()->addToOrder($cart, $item);

        self::assertCount(1, $cart->getItems());
    }

    public function testASignedInCustomerAddsToACartWhateverTheMode(): void
    {
        $this->signIn();
        foreach ([AccessMode::PricesHidden, AccessMode::Closed] as $mode) {
            $this->setMode($mode);
            [$cart, $item] = $this->cartAndItem();

            $this->modifier()->addToOrder($cart, $item);

            self::assertCount(1, $cart->getItems(), $mode->value);
        }
    }

    public function testNothingIsRestrictedWithoutARequest(): void
    {
        $this->setMode(AccessMode::Closed);
        $requestStack = self::getContainer()->get('request_stack');
        self::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->pop();

        $resolver = self::getContainer()->get(AccessModeResolverInterface::class);
        self::assertInstanceOf(AccessModeResolverInterface::class, $resolver);
        self::assertSame(AccessMode::Open, $resolver->currentMode(), 'console commands and message handlers are not restricted');
        self::assertTrue($resolver->canSeePrices());
    }

    private function modifier(): OrderModifierInterface
    {
        $modifier = self::getContainer()->get('sylius.modifier.order');
        self::assertInstanceOf(OrderModifierInterface::class, $modifier);

        return $modifier;
    }

    private function signIn(): void
    {
        $storage = self::getContainer()->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $storage);
        $user = $this->shopUser();
        $storage->setToken(new UsernamePasswordToken($user, 'shop', $user->getRoles()));
    }

    /** @return array{OrderInterface, OrderItemInterface} */
    private function cartAndItem(): array
    {
        /** @var FactoryInterface<OrderInterface> $orderFactory */
        $orderFactory = self::getContainer()->get('sylius.factory.order');
        /** @var FactoryInterface<OrderItemInterface> $itemFactory */
        $itemFactory = self::getContainer()->get('sylius.factory.order_item');
        $cart = $orderFactory->createNew();
        $cart->setChannel($this->channel());
        $cart->setCurrencyCode((string) $this->channel()->getBaseCurrency()?->getCode());
        $cart->setLocaleCode($this->localeCode());
        $item = $itemFactory->createNew();
        $product = $this->entityManager()->getRepository(ProductInterface::class)->findOneBy(['enabled' => true]);
        self::assertInstanceOf(ProductInterface::class, $product);
        $variant = $product->getEnabledVariants()->first();
        self::assertInstanceOf(ProductVariantInterface::class, $variant);
        $item->setVariant($variant);
        $item->setUnitPrice(1000);
        $quantityModifier = self::getContainer()->get('sylius.modifier.order_item_quantity');
        self::assertInstanceOf(OrderItemQuantityModifierInterface::class, $quantityModifier);
        $quantityModifier->modify($item, 1);

        return [$cart, $item];
    }
}
