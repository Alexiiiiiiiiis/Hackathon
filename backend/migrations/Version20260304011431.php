<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260304011431 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE project DROP git_url');
        $this->addSql('ALTER TABLE project DROP language');
        $this->addSql('ALTER TABLE project DROP status');
        $this->addSql('ALTER TABLE scan_result DROP tool');
        $this->addSql('ALTER TABLE scan_result DROP raw_json');
        $this->addSql('ALTER TABLE scan_result DROP created_at');
        $this->addSql('ALTER TABLE vulnerability DROP CONSTRAINT fk_vulnerability_project_id_legacy');
        $this->addSql('DROP INDEX idx_vulnerability_project_id_legacy');
        $this->addSql('ALTER TABLE vulnerability DROP file');
        $this->addSql('ALTER TABLE vulnerability DROP description');
        $this->addSql('ALTER TABLE vulnerability DROP tool');
        $this->addSql('ALTER TABLE vulnerability DROP project_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA pgbouncer');
        $this->addSql('CREATE SCHEMA realtime');
        $this->addSql('CREATE SCHEMA extensions');
        $this->addSql('CREATE SCHEMA vault');
        $this->addSql('CREATE SCHEMA graphql_public');
        $this->addSql('CREATE SCHEMA graphql');
        $this->addSql('CREATE SCHEMA auth');
        $this->addSql('CREATE SCHEMA storage');
        $this->addSql('ALTER TABLE project ADD git_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD language VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD status VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE scan_result ADD tool VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE scan_result ADD raw_json JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE scan_result ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE vulnerability ADD file VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE vulnerability ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE vulnerability ADD tool VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE vulnerability ADD project_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE vulnerability ADD CONSTRAINT fk_vulnerability_project_id_legacy FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_vulnerability_project_id_legacy ON vulnerability (project_id)');
    }
}
