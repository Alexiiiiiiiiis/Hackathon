<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260303124500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute des colonnes legacy pour compatibilite avec ancien code backend';
    }

    public function up(Schema $schema): void
    {
        // project legacy columns
        $this->addSql('ALTER TABLE project ADD git_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD language VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD status VARCHAR(50) DEFAULT NULL');
        $this->addSql('UPDATE project SET git_url = COALESCE(git_url, source)');
        $this->addSql('UPDATE project SET language = COALESCE(language, detected_language)');
        $this->addSql("UPDATE project SET status = COALESCE(status, 'pending')");

        // scan_result legacy columns
        $this->addSql('ALTER TABLE scan_result ADD tool VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE scan_result ADD raw_json JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE scan_result ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE scan_result SET created_at = COALESCE(created_at, started_at)');
        $this->addSql("UPDATE scan_result SET tool = COALESCE(tool, 'aggregated')");

        // vulnerability legacy columns
        $this->addSql('ALTER TABLE vulnerability ADD file VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE vulnerability ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE vulnerability ADD tool VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE vulnerability ADD project_id INT DEFAULT NULL');
        $this->addSql('UPDATE vulnerability SET file = COALESCE(file, file_path)');
        $this->addSql('UPDATE vulnerability SET description = COALESCE(description, message)');
        $this->addSql('UPDATE vulnerability SET tool = COALESCE(tool, tool_source)');
        $this->addSql('
            UPDATE vulnerability v
            SET project_id = s.project_id
            FROM scan_result s
            WHERE s.id = v.scan_result_id
        ');
        $this->addSql('CREATE INDEX IDX_VULNERABILITY_PROJECT_ID_LEGACY ON vulnerability (project_id)');
        $this->addSql('ALTER TABLE vulnerability ADD CONSTRAINT FK_VULNERABILITY_PROJECT_ID_LEGACY FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vulnerability DROP CONSTRAINT FK_VULNERABILITY_PROJECT_ID_LEGACY');
        $this->addSql('DROP INDEX IDX_VULNERABILITY_PROJECT_ID_LEGACY');
        $this->addSql('ALTER TABLE vulnerability DROP project_id');
        $this->addSql('ALTER TABLE vulnerability DROP tool');
        $this->addSql('ALTER TABLE vulnerability DROP description');
        $this->addSql('ALTER TABLE vulnerability DROP file');

        $this->addSql('ALTER TABLE scan_result DROP created_at');
        $this->addSql('ALTER TABLE scan_result DROP raw_json');
        $this->addSql('ALTER TABLE scan_result DROP tool');

        $this->addSql('ALTER TABLE project DROP status');
        $this->addSql('ALTER TABLE project DROP language');
        $this->addSql('ALTER TABLE project DROP git_url');
    }
}

