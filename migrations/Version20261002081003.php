<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * L'org attiva della sessione porta l'id di organizations (BIGINT): la colonna era INT e un id oltre
 * i 2 miliardi sarebbe stato troncato o rifiutato. Nessun dato da convertire, solo il tipo.
 */
final class Version20261002081003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refresh_tokens.active_organization_id — da INT a BIGINT, come organizations.id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_tokens CHANGE active_organization_id active_organization_id BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_tokens CHANGE active_organization_id active_organization_id INT DEFAULT NULL');
    }
}
