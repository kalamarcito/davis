<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260215120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add granular permissions field to calendar and address book instances';
    }

    public function up(Schema $schema): void
    {
        $calendarCols = $schema->getTable('calendarinstances')->getColumns();
        if (!isset($calendarCols['permissions'])) {
            $this->addSql('ALTER TABLE calendarinstances ADD permissions SMALLINT DEFAULT 0 NOT NULL');
        }

        $addressBookCols = $schema->getTable('addressbookinstances')->getColumns();
        if (!isset($addressBookCols['permissions'])) {
            $this->addSql('ALTER TABLE addressbookinstances ADD permissions SMALLINT DEFAULT 0 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE calendarinstances DROP COLUMN permissions');
        $this->addSql('ALTER TABLE addressbookinstances DROP COLUMN permissions');
    }
}
