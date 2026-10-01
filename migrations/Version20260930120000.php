<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * L'org attiva vive sulla sessione (refresh token): un refresh non riporta più alla prima org.
 */
final class Version20260930120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refresh_tokens.active_organization_id — org attiva persistita tra i refresh';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_tokens ADD active_organization_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_tokens DROP active_organization_id');
    }
}
