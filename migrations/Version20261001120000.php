<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ogni veicolo ha un proprietario (share `admin`): totali e grafici della dashboard, scadenze
 * in arrivo e notifiche riguardano solo i veicoli propri, anche per owner/admin dell'org.
 * Finora i veicoli creati da un owner/admin dell'org nascevano senza proprietario: qui lo si
 * assegna a chi li ha creati (dall'audit log, se è ancora membro), altrimenti al primo owner
 * dell'org. Le vecchie condivisioni `editor` diventano `viewer`: una condivisione è sempre in
 * sola lettura.
 */
final class Version20261001120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'vehicle_shares — proprietario per ogni veicolo, condivisioni editor in sola lettura';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE vehicle_shares SET role = 'viewer' WHERE role = 'editor'");

        $owners = $this->connection->fetchAllAssociative(<<<'SQL'
            SELECT v.id AS vehicle_id, COALESCE(
                (SELECT a.user_id FROM audit_logs a
                    INNER JOIN organization_members m
                        ON m.user_id = a.user_id AND m.organization_id = v.organization_id AND m.accepted_at IS NOT NULL
                    WHERE a.entity_class = 'Vehicle' AND a.entity_id = CAST(v.id AS CHAR) AND a.action = 'created'
                    ORDER BY a.id ASC LIMIT 1),
                (SELECT m.user_id FROM organization_members m
                    WHERE m.organization_id = v.organization_id AND m.role = 'owner' AND m.accepted_at IS NOT NULL
                    ORDER BY m.id ASC LIMIT 1)
            ) AS user_id
            FROM vehicles v
            WHERE NOT EXISTS (SELECT 1 FROM vehicle_shares s WHERE s.vehicle_id = v.id AND s.role = 'admin')
            SQL);

        foreach ($owners as $row) {
            if ($row['user_id'] === null) {
                continue; // org senza owner accettato: nessuno a cui assegnarlo
            }
            $params = ['vehicle' => (int) $row['vehicle_id'], 'user' => (int) $row['user_id']];

            // Se il futuro proprietario aveva già una condivisione (unique vehicle+user), la si promuove.
            $this->addSql(
                "UPDATE vehicle_shares SET role = 'admin', accepted_at = COALESCE(accepted_at, NOW())
                 WHERE vehicle_id = :vehicle AND user_id = :user",
                $params,
            );
            $this->addSql(
                "INSERT INTO vehicle_shares (vehicle_id, user_id, role, invited_by, accepted_at, created_at)
                 SELECT :vehicle, :user, 'admin', :user, NOW(), NOW() FROM DUAL
                 WHERE NOT EXISTS (SELECT 1 FROM vehicle_shares WHERE vehicle_id = :vehicle AND user_id = :user)",
                $params,
            );
        }
    }

    public function down(Schema $schema): void
    {
        // Non reversibile con precisione: non si distinguono le proprietà assegnate qui da quelle
        // nate con il veicolo, né le condivisioni che erano `editor`. Lasciarle è innocuo.
    }
}
