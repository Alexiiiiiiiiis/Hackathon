<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260303120740 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Align legacy schema to new scan-centric model without breaking existing rows.
        $this->addSql("ALTER TABLE fix ADD generated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW()");
        $this->addSql('ALTER TABLE fix ADD applied_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE fix ADD scan_result_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE fix ALTER explanation SET NOT NULL');
        $this->addSql('ALTER TABLE fix ALTER status TYPE VARCHAR(20)');

        $this->addSql('ALTER TABLE project ADD name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD source VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD source_type VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD local_path VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD detected_language VARCHAR(50) DEFAULT NULL');
        $this->addSql("UPDATE project SET name = COALESCE(NULLIF(name, ''), 'Projet sans nom')");
        $this->addSql("UPDATE project SET source = COALESCE(source, git_url)");
        $this->addSql("UPDATE project SET source_type = COALESCE(NULLIF(source_type, ''), 'github')");
        $this->addSql('ALTER TABLE project ALTER name SET NOT NULL');
        $this->addSql('ALTER TABLE project ALTER source SET NOT NULL');
        $this->addSql('ALTER TABLE project ALTER source_type SET NOT NULL');
        $this->addSql('ALTER TABLE project DROP git_url');
        $this->addSql('ALTER TABLE project DROP language');
        $this->addSql('ALTER TABLE project DROP status');

        $this->addSql("ALTER TABLE scan_result ADD status VARCHAR(20) NOT NULL DEFAULT 'completed'");
        $this->addSql('ALTER TABLE scan_result ADD global_score INT DEFAULT NULL');
        $this->addSql('ALTER TABLE scan_result ADD grade VARCHAR(2) DEFAULT NULL');
        $this->addSql('ALTER TABLE scan_result ADD finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE scan_result DROP tool');
        $this->addSql('ALTER TABLE scan_result DROP raw_json');
        $this->addSql('ALTER TABLE scan_result RENAME COLUMN created_at TO started_at');

        $this->addSql('ALTER TABLE vulnerability DROP CONSTRAINT fk_6c4e4047166d1f9c');
        $this->addSql('DROP INDEX idx_6c4e4047166d1f9c');
        $this->addSql('ALTER TABLE vulnerability ADD tool_source VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE vulnerability ADD rule_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE vulnerability ADD file_path VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE vulnerability ADD code_snippet TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE vulnerability ADD suggested_fix TEXT DEFAULT NULL');
        $this->addSql("UPDATE vulnerability SET tool_source = COALESCE(tool_source, tool, 'legacy')");
        $this->addSql("UPDATE vulnerability SET rule_id = COALESCE(rule_id, CONCAT('legacy-', id::text))");
        $this->addSql('ALTER TABLE vulnerability ALTER tool_source SET NOT NULL');
        $this->addSql('ALTER TABLE vulnerability ALTER rule_id SET NOT NULL');
        $this->addSql('ALTER TABLE vulnerability DROP file');
        $this->addSql('ALTER TABLE vulnerability DROP tool');
        $this->addSql('ALTER TABLE vulnerability ALTER severity TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE vulnerability ALTER owasp_category TYPE VARCHAR(255)');
        $this->addSql("UPDATE vulnerability SET owasp_category = 'UNKNOWN' WHERE owasp_category IS NULL");
        $this->addSql('ALTER TABLE vulnerability ALTER owasp_category SET NOT NULL');
        $this->addSql('ALTER TABLE vulnerability ALTER fix_status TYPE VARCHAR(20)');
        $this->addSql('ALTER TABLE vulnerability RENAME COLUMN description TO message');
        $this->addSql('ALTER TABLE vulnerability RENAME COLUMN project_id TO scan_result_id');

        $this->addSql('
            UPDATE vulnerability v
            SET scan_result_id = s.id
            FROM scan_result s
            WHERE s.project_id = v.scan_result_id
              AND s.id = (
                SELECT s2.id
                FROM scan_result s2
                WHERE s2.project_id = v.scan_result_id
                ORDER BY s2.started_at DESC NULLS LAST, s2.id DESC
                LIMIT 1
              )
        ');

        $this->addSql('ALTER TABLE vulnerability ADD CONSTRAINT FK_6C4E4047EC68BBB8 FOREIGN KEY (scan_result_id) REFERENCES scan_result (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_6C4E4047EC68BBB8 ON vulnerability (scan_result_id)');

        $this->addSql('
            UPDATE fix f
            SET scan_result_id = v.scan_result_id
            FROM vulnerability v
            WHERE v.id = f.vulnerability_id
        ');
        $this->addSql('ALTER TABLE fix ALTER scan_result_id SET NOT NULL');
        $this->addSql('ALTER TABLE fix ADD CONSTRAINT FK_59FA4760EC68BBB8 FOREIGN KEY (scan_result_id) REFERENCES scan_result (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_59FA4760EC68BBB8 ON fix (scan_result_id)');
        $this->addSql('ALTER TABLE fix ALTER generated_at DROP DEFAULT');
        $this->addSql('ALTER TABLE scan_result ALTER status DROP DEFAULT');
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
        $this->addSql('ALTER TABLE fix DROP CONSTRAINT FK_59FA4760EC68BBB8');
        $this->addSql('DROP INDEX IDX_59FA4760EC68BBB8');
        $this->addSql('ALTER TABLE fix DROP generated_at');
        $this->addSql('ALTER TABLE fix DROP applied_at');
        $this->addSql('ALTER TABLE fix DROP scan_result_id');
        $this->addSql('ALTER TABLE fix ALTER explanation DROP NOT NULL');
        $this->addSql('ALTER TABLE fix ALTER status TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE project ADD git_url VARCHAR(500) NOT NULL');
        $this->addSql('ALTER TABLE project ADD language VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE project ADD status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE project DROP name');
        $this->addSql('ALTER TABLE project DROP source');
        $this->addSql('ALTER TABLE project DROP source_type');
        $this->addSql('ALTER TABLE project DROP local_path');
        $this->addSql('ALTER TABLE project DROP detected_language');
        $this->addSql('ALTER TABLE scan_result ADD tool VARCHAR(100) NOT NULL');
        $this->addSql('ALTER TABLE scan_result ADD raw_json JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE scan_result DROP status');
        $this->addSql('ALTER TABLE scan_result DROP global_score');
        $this->addSql('ALTER TABLE scan_result DROP grade');
        $this->addSql('ALTER TABLE scan_result DROP finished_at');
        $this->addSql('ALTER TABLE scan_result RENAME COLUMN started_at TO created_at');
        $this->addSql('ALTER TABLE vulnerability DROP CONSTRAINT FK_6C4E4047EC68BBB8');
        $this->addSql('DROP INDEX IDX_6C4E4047EC68BBB8');
        $this->addSql('ALTER TABLE vulnerability ADD file VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE vulnerability ADD tool VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE vulnerability DROP tool_source');
        $this->addSql('ALTER TABLE vulnerability DROP rule_id');
        $this->addSql('ALTER TABLE vulnerability DROP file_path');
        $this->addSql('ALTER TABLE vulnerability DROP code_snippet');
        $this->addSql('ALTER TABLE vulnerability DROP suggested_fix');
        $this->addSql('ALTER TABLE vulnerability ALTER severity TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE vulnerability ALTER owasp_category TYPE VARCHAR(10)');
        $this->addSql('ALTER TABLE vulnerability ALTER owasp_category DROP NOT NULL');
        $this->addSql('ALTER TABLE vulnerability ALTER fix_status TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE vulnerability RENAME COLUMN message TO description');
        $this->addSql('ALTER TABLE vulnerability RENAME COLUMN scan_result_id TO project_id');
        $this->addSql('ALTER TABLE vulnerability ADD CONSTRAINT fk_6c4e4047166d1f9c FOREIGN KEY (project_id) REFERENCES project (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_6c4e4047166d1f9c ON vulnerability (project_id)');
    }
}
