<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rilevamento del riuso dei refresh token: ogni token appartiene a una famiglia (`family_id`, la sessione di
 * login) e `rotated_at` distingue "sostituito da una rotazione" da "revocato per altro" (logout, cambio password).
 *
 * Backfill: ogni token esistente apre una famiglia tutta sua (UUID senza trattini, 32 caratteri), quindi le
 * sessioni attive restano valide. `rotated_at` resta NULL sui token già revocati: non si può sapere se lo siano
 * stati per rotazione, e senza il dato non contano come riuso (nessun logout forzato su dati incerti).
 * Gli statement usano IF [NOT] EXISTS: MariaDB non ha DDL transazionale, un'esecuzione interrotta a metà
 * può essere rilanciata senza errori.
 */
final class Version20261005130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refresh_tokens.family_id e rotated_at — famiglie di sessione e rilevamento del riuso dei refresh token';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_tokens ADD COLUMN IF NOT EXISTS family_id VARCHAR(32) DEFAULT NULL, ADD COLUMN IF NOT EXISTS rotated_at DATETIME DEFAULT NULL');
        $this->addSql("UPDATE refresh_tokens SET family_id = REPLACE(UUID(), '-', '') WHERE family_id IS NULL");
        $this->addSql('ALTER TABLE refresh_tokens MODIFY family_id VARCHAR(32) NOT NULL');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_refresh_tokens_family ON refresh_tokens (family_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_refresh_tokens_family ON refresh_tokens');
        $this->addSql('ALTER TABLE refresh_tokens DROP COLUMN IF EXISTS family_id, DROP COLUMN IF EXISTS rotated_at');
    }
}
