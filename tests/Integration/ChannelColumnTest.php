<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusChannelAccessPlugin\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ChannelColumnTest extends KernelTestCase
{
    public function testTheMigrationAddedAnOpenByDefaultColumn(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        $column = $connection->createSchemaManager()->introspectTable('sylius_channel')->getColumn('access_mode');

        self::assertTrue($column->getNotnull());
        self::assertSame('open', $column->getDefault());
        self::assertSame(20, $column->getLength());
    }
}
