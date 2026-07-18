<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Group principals: is_group flag + optional source (manual|ldap).
 * Group membership reuses groupmembers (same as calendar-proxy delegees).
 */
final class Version20260718000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_group and group_source columns to principals for group sharing';
    }

    public function up(Schema $schema): void
    {
        $cols = $schema->getTable('principals')->getColumns();
        if (!isset($cols['is_group'])) {
            $this->addSql('ALTER TABLE principals ADD is_group TINYINT(1) DEFAULT 0 NOT NULL');
        }
        if (!isset($cols['group_source'])) {
            $this->addSql('ALTER TABLE principals ADD group_source VARCHAR(16) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE principals DROP COLUMN is_group');
        $this->addSql('ALTER TABLE principals DROP COLUMN group_source');
    }
}
