<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin\Functional;

use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Tmonier\SyliusChannelAccessPlugin\Access\AccessMode;

final class AdminChannelFormTest extends WebTestCase
{
    use FunctionalTestTrait;

    protected function setUp(): void
    {
        $this->startScenario();
        $admin = $this->entityManager()->getRepository(AdminUserInterface::class)->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin);
        $this->client->loginUser($admin, 'admin');
    }

    public function testTheEditFormOffersTheThreeModesAndPreselectsTheCurrentOne(): void
    {
        $this->setMode(AccessMode::PricesHidden);

        $crawler = $this->client->request('GET', $this->editUrl());

        self::assertResponseIsSuccessful();
        $select = $crawler->filter('[data-test-tmonier-access-mode] select');
        self::assertCount(1, $select);
        self::assertSame(['open', 'prices_hidden', 'closed'], $select->filter('option')->each(static fn (Crawler $option): string => (string) $option->attr('value')));
        self::assertSame('prices_hidden', $select->filter('option[selected]')->attr('value'));
    }

    public function testTheCreateFormOffersTheField(): void
    {
        $crawler = $this->client->request('GET', '/admin/channels/new');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-test-tmonier-access-mode] select'));
    }

    public function testAModeIsSavedAndReadBack(): void
    {
        $crawler = $this->client->request('GET', $this->editUrl());
        $form = $crawler->filter('form[name="sylius_admin_channel"]')->form();
        $form['sylius_admin_channel[accessMode]'] = 'closed';
        $this->client->submit($form);

        self::assertSame(AccessMode::Closed, $this->reloaded()->getAccessMode());
    }

    public function testAnInvalidModeIsRefusedAndThePreviousModeKept(): void
    {
        $this->setMode(AccessMode::PricesHidden);
        $crawler = $this->client->request('GET', $this->editUrl());
        $form = $crawler->filter('form[name="sylius_admin_channel"]')->form();
        $values = $form->getPhpValues();
        $values['sylius_admin_channel'] = array_merge(\is_array($values['sylius_admin_channel'] ?? null) ? $values['sylius_admin_channel'] : [], ['accessMode' => 'invite_only']);
        $crawler = $this->client->request($form->getMethod(), $form->getUri(), $values, [], []);

        self::assertStringContainsString('Choose open, prices hidden or closed.', $crawler->html());
        self::assertSame(AccessMode::PricesHidden, $this->reloaded()->getAccessMode());
    }

    private function editUrl(): string
    {
        return '/admin/channels/' . $this->channelId() . '/edit';
    }

    private function reloaded(): \Tmonier\SyliusChannelAccessPlugin\Entity\Channel\AccessModeAwareInterface
    {
        $id = $this->channelId();
        $this->entityManager()->clear();
        $channel = $this->entityManager()->find($this->channel()::class, $id);
        self::assertInstanceOf(\Tmonier\SyliusChannelAccessPlugin\Entity\Channel\AccessModeAwareInterface::class, $channel);

        return $channel;
    }
}
