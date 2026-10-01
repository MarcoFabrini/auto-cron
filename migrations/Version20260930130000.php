<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * I refresh token passano da valore in chiaro a sha256 (come già reset/verifica/inviti): un dump o un
 * backup del DB non permettono più di rubare le sessioni. I token esistenti vengono convertiti sul posto,
 * quindi le sessioni aperte restano valide (il client continua a presentare il valore in chiaro).
 */
final class Version20260930130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refresh_tokens.token — da valore in chiaro a sha256';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE refresh_tokens SET token = SHA2(token, 256) WHERE CHAR_LENGTH(token) = 96");
    }

    public function down(Schema $schema): void
    {
        // Un hash non è invertibile: le sessioni attive vengono revocate (gli utenti dovranno riloggarsi).
        $this->addSql('UPDATE refresh_tokens SET revoked_at = NOW() WHERE revoked_at IS NULL');
    }
}
