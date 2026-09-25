<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * SMTP di istanza torna a configurarsi solo da env (MAILER_DSN): niente più
 * config in DB, la tabella (con la password cifrata a riposo) non serve più.
 */
final class Version20260923120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop mail_settings — SMTP torna a essere solo env (MAILER_DSN), niente più config in DB';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mail_settings DROP FOREIGN KEY FK_3892618D896DBBDE');
        $this->addSql('DROP TABLE mail_settings');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mail_settings (id BIGINT AUTO_INCREMENT NOT NULL, host VARCHAR(255) NOT NULL, port INT NOT NULL, encryption VARCHAR(10) NOT NULL, username VARCHAR(255) DEFAULT NULL, password_cipher LONGTEXT DEFAULT NULL, from_address VARCHAR(180) NOT NULL, from_name VARCHAR(100) DEFAULT NULL, enabled TINYINT DEFAULT 0 NOT NULL, updated_at DATETIME NOT NULL, updated_by_id BIGINT DEFAULT NULL, INDEX IDX_3892618D896DBBDE (updated_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mail_settings ADD CONSTRAINT FK_3892618D896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
    }
}
