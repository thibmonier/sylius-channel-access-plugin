<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin\Unit\Access;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Core\Model\Channel as CoreChannel;
use Sylius\Component\Core\Model\ShopUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Tests\Tmonier\SyliusChannelAccessPlugin\Entity\Channel\Channel;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessModeResolver;

final class AccessModeResolverTest extends TestCase
{
    /** @return iterable<string, array{AccessMode, bool, bool, bool}> mode, signed in, sees catalogue, sees prices */
    public static function matrix(): iterable
    {
        yield 'open, anonymous' => [AccessMode::Open, false, true, true];
        yield 'open, signed in' => [AccessMode::Open, true, true, true];
        yield 'prices hidden, anonymous' => [AccessMode::PricesHidden, false, true, false];
        yield 'prices hidden, signed in' => [AccessMode::PricesHidden, true, true, true];
        yield 'closed, anonymous' => [AccessMode::Closed, false, false, false];
        yield 'closed, signed in' => [AccessMode::Closed, true, true, true];
    }

    #[DataProvider('matrix')]
    public function testWhatAVisitorSees(AccessMode $mode, bool $signedIn, bool $catalogue, bool $prices): void
    {
        $resolver = $this->resolver($mode, $signedIn);

        self::assertSame($mode, $resolver->currentMode());
        self::assertSame($signedIn, $resolver->isVisitorSignedIn());
        self::assertSame($catalogue, $resolver->canSeeCatalog());
        self::assertSame($prices, $resolver->canSeePrices());
    }

    public function testAnObjectThatIsNotASyliusUserIsAnonymous(): void
    {
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken(new InMemoryUser('api', null, ['ROLE_USER']), 'main', ['ROLE_USER']));

        self::assertFalse($this->resolver(AccessMode::Closed, false, $storage)->isVisitorSignedIn());
    }

    public function testAChannelWithoutTheTraitIsClosedAndTheMisconfigurationIsLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $resolver = new AccessModeResolver($this->context(new CoreChannel()), new TokenStorage(), $this->requests(), $logger);

        self::assertSame(AccessMode::Closed, $resolver->currentMode());
        self::assertFalse($resolver->canSeePrices());
    }

    public function testAnHttpRequestWithoutChannelFailsClosed(): void
    {
        $context = $this->createStub(ChannelContextInterface::class);
        $context->method('getChannel')->willThrowException(new ChannelNotFoundException());
        $resolver = new AccessModeResolver($context, new TokenStorage(), $this->requests());

        self::assertSame(AccessMode::Closed, $resolver->currentMode());
        self::assertFalse($resolver->canSeeCatalog());
        self::assertFalse($resolver->canSeePrices());
    }

    public function testNoRequestMeansNoRestriction(): void
    {
        $channel = new Channel();
        $channel->setAccessMode(AccessMode::Closed);

        self::assertSame(AccessMode::Open, (new AccessModeResolver($this->context($channel), new TokenStorage(), new RequestStack()))->currentMode());
    }

    private function resolver(AccessMode $mode, bool $signedIn, ?TokenStorage $storage = null): AccessModeResolver
    {
        $channel = new Channel();
        $channel->setAccessMode($mode);
        if (!$storage instanceof \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage) {
            $storage = new TokenStorage();
            if ($signedIn) {
                $user = new ShopUser();
                $user->setUsername('ada@example.com');
                $storage->setToken(new UsernamePasswordToken($user, 'shop', ['ROLE_USER']));
            }
        }

        return new AccessModeResolver($this->context($channel), $storage, $this->requests());
    }

    private function context(object $channel): ChannelContextInterface
    {
        $context = $this->createStub(ChannelContextInterface::class);
        $context->method('getChannel')->willReturn($channel);

        return $context;
    }

    private function requests(): RequestStack
    {
        $stack = new RequestStack();
        $stack->push(new Request());

        return $stack;
    }
}
