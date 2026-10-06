<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le spese ricorrenti ora si contano a ogni addebito: serve la data dell'ultimo addebito possibile,
 * altrimenti un abbonamento disdetto verrebbe contato per sempre. NULL = ancora in corso, quindi le
 * spese esistenti restano valide senza backfill.
 */
final class Version20261005090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'expenses.recurring_until — data dell\'ultimo addebito possibile (inclusa) delle spese ricorrenti';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expenses ADD recurring_until DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expenses DROP recurring_until');
    }
}
