<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Notifiche dei promemoria per livello di urgenza (in scadenza → scaduto) invece che una al giorno:
 * `notified_urgency` tiene l'ultimo livello notificato.
 *
 * Per i promemoria già notificati con la vecchia logica (last_notified_at valorizzato) si parte da
 * "soon", così al deploy non arriva una seconda notifica per lo stesso livello.
 */
final class Version20260925120255 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'reminders.notified_urgency — una notifica per livello di urgenza, non una al giorno';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reminders ADD notified_urgency VARCHAR(10) DEFAULT NULL');
        $this->addSql("UPDATE reminders SET notified_urgency = 'soon' WHERE last_notified_at IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reminders DROP notified_urgency');
    }
}
