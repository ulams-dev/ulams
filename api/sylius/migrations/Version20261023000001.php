<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** ulams bridge: outbox, channel links and the customer's link to an LMS user (ADR 0130, 0131, 0132). */
final class Version20261023000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ulams bridge: outbox, channel links, customer ulams_user_ref';
    }

    public function up(Schema $schema): void
    {
        $outbox = $schema->createTable('ulams_outbox_event');
        $outbox->addColumn('seq', 'bigint', ['autoincrement' => true]);
        $outbox->addColumn('id', 'string', ['length' => 26]);
        $outbox->addColumn('channel_code', 'string', ['length' => 64]);
        $outbox->addColumn('order_token', 'string', ['length' => 64]);
        $outbox->addColumn('sequence', 'integer');
        $outbox->addColumn('type', 'string', ['length' => 32]);
        $outbox->addColumn('payload', 'json');
        $outbox->addColumn('created_at', 'datetime_immutable');
        $outbox->addColumn('delivered_at', 'datetime_immutable', ['notnull' => false]);
        $outbox->addColumn('attempts', 'integer', ['default' => 0]);
        $outbox->addColumn('next_attempt_at', 'datetime_immutable', ['notnull' => false]);
        $outbox->addColumn('last_error', 'text', ['notnull' => false]);
        $outbox->setPrimaryKey(['seq']);
        $outbox->addUniqueIndex(['id'], 'UNIQ_B305B055BF396750');
        $outbox->addUniqueIndex(['order_token', 'sequence'], 'ulams_outbox_order_sequence');
        $outbox->addIndex(['channel_code', 'seq'], 'ulams_outbox_channel_seq');
        $outbox->addIndex(['delivered_at', 'next_attempt_at'], 'ulams_outbox_pending');

        $link = $schema->createTable('ulams_channel_link');
        $link->addColumn('channel_code', 'string', ['length' => 64]);
        $link->addColumn('webhook_url', 'string', ['length' => 500]);
        $link->addColumn('sealed_secret', 'text');
        $link->addColumn('updated_at', 'datetime_immutable');
        $link->setPrimaryKey(['channel_code']);

        $customer = $schema->getTable('sylius_customer');
        $customer->addColumn('ulams_user_ref', 'string', ['length' => 191, 'notnull' => false]);
        $customer->addUniqueIndex(['ulams_user_ref'], 'UNIQ_7E82D5E629470C6F');
    }

    public function down(Schema $schema): void
    {
        $customer = $schema->getTable('sylius_customer');
        $customer->dropIndex('UNIQ_7E82D5E629470C6F');
        $customer->dropColumn('ulams_user_ref');
        $schema->dropTable('ulams_channel_link');
        $schema->dropTable('ulams_outbox_event');
    }
}
