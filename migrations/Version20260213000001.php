<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add AddressBook sharing support.
 * 
 * This migration:
 * 1. Creates the addressbookinstances table
 * 2. Migrates existing data from addressbooks to addressbookinstances
 * 3. Removes the migrated columns from addressbooks table
 */
final class Version20260213000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add AddressBook sharing support by creating addressbookinstances table and migrating data';
    }

    public function up(Schema $schema): void
    {
        // Create addressbookinstances table
        $this->addSql('CREATE TABLE addressbookinstances (
            id INT AUTO_INCREMENT NOT NULL, 
            addressbookid INT NOT NULL, 
            principaluri VARCHAR(255) DEFAULT NULL, 
            access SMALLINT DEFAULT 1 NOT NULL, 
            displayname VARCHAR(255) DEFAULT NULL, 
            uri VARCHAR(200) DEFAULT NULL, 
            description LONGTEXT DEFAULT NULL, 
            share_href VARCHAR(100) DEFAULT NULL, 
            share_displayname VARCHAR(255) DEFAULT NULL, 
            share_invitestatus INT DEFAULT 2 NOT NULL, 
            UNIQUE INDEX UNIQ_addressbookinstances_principaluri_uri (principaluri, uri), 
            UNIQUE INDEX UNIQ_addressbookinstances_addressbookid_principaluri (addressbookid, principaluri), 
            UNIQUE INDEX UNIQ_addressbookinstances_addressbookid_share_href (addressbookid, share_href), 
            INDEX IDX_addressbookinstances_addressbookid (addressbookid), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Add foreign key constraint
        $this->addSql('ALTER TABLE addressbookinstances ADD CONSTRAINT FK_addressbookinstances_addressbookid FOREIGN KEY (addressbookid) REFERENCES addressbooks (id)');

        // Migrate existing data from addressbooks to addressbookinstances (if any rows exist)
        $this->addSql('INSERT INTO addressbookinstances (addressbookid, principaluri, access, displayname, uri, description, share_invitestatus) 
                      SELECT id, principaluri, 1, displayname, uri, description, 2 FROM addressbooks');

        // Remove migrated columns from addressbooks table (use IF EXISTS for fresh installs)
        $this->addSql('ALTER TABLE addressbooks DROP IF EXISTS principaluri');
        $this->addSql('ALTER TABLE addressbooks DROP IF EXISTS displayname');
        $this->addSql('ALTER TABLE addressbooks DROP IF EXISTS uri');
        $this->addSql('ALTER TABLE addressbooks DROP IF EXISTS description');
        
        // Drop the unique constraint if it exists (may not exist on fresh installs)
        $this->addSql('SET @exist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'addressbooks\' AND INDEX_NAME = \'UNIQ_addressbooks_principaluri_uri\')');
        $this->addSql('SET @sqlstmt := IF(@exist > 0, \'ALTER TABLE addressbooks DROP INDEX UNIQ_addressbooks_principaluri_uri\', \'SELECT 1\')');
        $this->addSql('PREPARE stmt FROM @sqlstmt');
        $this->addSql('EXECUTE stmt');
    }

    public function down(Schema $schema): void
    {
        // Add back the columns to addressbooks
        $this->addSql('ALTER TABLE addressbooks ADD principaluri VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE addressbooks ADD displayname VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE addressbooks ADD uri VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE addressbooks ADD description LONGTEXT DEFAULT NULL');

        // Migrate data back from addressbookinstances to addressbooks
        // Only migrate owner instances (access = 1)
        $this->addSql('UPDATE addressbooks a 
                      INNER JOIN addressbookinstances ai ON a.id = ai.addressbookid 
                      SET a.principaluri = ai.principaluri, 
                          a.displayname = ai.displayname, 
                          a.uri = ai.uri, 
                          a.description = ai.description 
                      WHERE ai.access = 1');

        // Re-add the unique constraint
        $this->addSql('CREATE UNIQUE INDEX UNIQ_addressbooks_principaluri_uri ON addressbooks (principaluri, uri)');

        // Drop foreign key constraint and table
        $this->addSql('ALTER TABLE addressbookinstances DROP FOREIGN KEY FK_addressbookinstances_addressbookid');
        $this->addSql('DROP TABLE addressbookinstances');
    }
}