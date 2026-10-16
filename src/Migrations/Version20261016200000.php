<?php

declare(strict_types=1);

namespace Tmonier\SyliusChannelAccessPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Additive: one column on the channel table, existing channels stay open. Schema API (not raw
 * SQL) so that it runs on MySQL, MariaDB and PostgreSQL.
 */
final class Version20261016200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the access mode to the channels (tmonier/sylius-channel-access-plugin).';
    }

    public function up(Schema $schema): void
    {
        $channel = $schema->getTable('sylius_channel');
        $channel->addColumn('access_mode', Types::STRING, ['length' => 20, 'default' => 'open', 'notnull' => true]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('sylius_channel')->dropColumn('access_mode');
    }
}
