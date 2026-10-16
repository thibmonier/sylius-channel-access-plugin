<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;
use Tmonier\SyliusChannelAccessPlugin\Entity\Channel\AccessModeAwareInterface;
use Webmozart\Assert\Assert;

/**
 * Every test runs in a transaction rolled back at the end: the access mode of the channel is
 * changed inside it, the fixtures stay untouched.
 */
trait ChannelAccessTestTrait
{
    private function beginTestTransaction(): void
    {
        $this->connection()->beginTransaction();
    }

    private function rollBackTestTransaction(): void
    {
        if ($this->connection()->isTransactionActive()) {
            $this->connection()->rollBack();
        }
    }

    private function channel(): ChannelInterface&AccessModeAwareInterface
    {
        $repository = $this->entityManager()->getRepository(ChannelInterface::class);
        $channel = $repository->findOneBy(['hostname' => 'localhost']) ?? $repository->findOneBy(['enabled' => true]);
        Assert::isInstanceOf($channel, ChannelInterface::class);
        Assert::isInstanceOf($channel, AccessModeAwareInterface::class);

        return $channel;
    }

    private function channelId(): int
    {
        $id = $this->channel()->getId();
        Assert::integer($id);

        return $id;
    }

    private function setMode(AccessMode $mode): void
    {
        $channel = $this->channel();
        $channel->setAccessMode($mode);
        $this->entityManager()->flush();
    }

    private function localeCode(): string
    {
        return (string) $this->channel()->getDefaultLocale()?->getCode();
    }

    /** Makes a locale available in the channel (created if needed) and returns its code. */
    private function offerLocale(string $code): string
    {
        $locale = $this->entityManager()->getRepository(LocaleInterface::class)->findOneBy(['code' => $code]);
        if (null === $locale) {
            /** @var FactoryInterface<LocaleInterface> $factory */
            $factory = self::getContainer()->get('sylius.factory.locale');
            $locale = $factory->createNew();
            $locale->setCode($code);
            $this->entityManager()->persist($locale);
        }
        $this->channel()->addLocale($locale);
        $this->entityManager()->flush();

        return $code;
    }

    private function shopUrl(string $path): string
    {
        return sprintf('/%s%s', $this->localeCode(), $path);
    }

    private function product(): ProductInterface
    {
        $product = $this->entityManager()->getRepository(ProductInterface::class)->findOneBy(['enabled' => true]);
        Assert::isInstanceOf($product, ProductInterface::class);

        return $product;
    }

    private function productUrl(): string
    {
        $slug = $this->product()->getTranslation($this->localeCode())->getSlug();

        return $this->shopUrl('/products/' . $slug);
    }

    private function shopUser(): ShopUserInterface
    {
        $user = $this->entityManager()->getRepository(ShopUserInterface::class)->findOneBy(['username' => 'shop@example.com']);
        Assert::isInstanceOf($user, ShopUserInterface::class);

        return $user;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        return $entityManager;
    }

    private function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }
}
