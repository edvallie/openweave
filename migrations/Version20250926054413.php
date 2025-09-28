<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250926054413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pattern (id SERIAL NOT NULL, title VARCHAR(255) DEFAULT NULL, created_by INT DEFAULT NULL, shafts INT NOT NULL, treadles INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, update_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, wif BYTEA DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN pattern.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN pattern.update_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE pattern_favorite (id SERIAL NOT NULL, pattern_id INT NOT NULL, account_id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_380B57F5F734A20F ON pattern_favorite (pattern_id)');
        $this->addSql('CREATE INDEX IDX_380B57F59B6B5FBA ON pattern_favorite (account_id)');
        $this->addSql('ALTER TABLE pattern_favorite ADD CONSTRAINT FK_380B57F5F734A20F FOREIGN KEY (pattern_id) REFERENCES pattern (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE pattern_favorite ADD CONSTRAINT FK_380B57F59B6B5FBA FOREIGN KEY (account_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE pattern_favorite DROP CONSTRAINT FK_380B57F5F734A20F');
        $this->addSql('ALTER TABLE pattern_favorite DROP CONSTRAINT FK_380B57F59B6B5FBA');
        $this->addSql('DROP TABLE pattern');
        $this->addSql('DROP TABLE pattern_favorite');
    }
}
