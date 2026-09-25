<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260917220335 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rimuove la feature "mano dominante": solo destrorsi da ora in poi.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP handedness');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD handedness VARCHAR(5) DEFAULT \'right\' NOT NULL');
    }
}
