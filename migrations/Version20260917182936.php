<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260917182936 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attachments (id BIGINT AUTO_INCREMENT NOT NULL, entity_type VARCHAR(30) NOT NULL, entity_id BIGINT NOT NULL, original_filename VARCHAR(255) NOT NULL, stored_path VARCHAR(500) NOT NULL, mime_type VARCHAR(100) NOT NULL, size_bytes INT NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, organization_id BIGINT NOT NULL, uploaded_by BIGINT NOT NULL, INDEX idx_attachments_org (organization_id), INDEX idx_attachments_entity (entity_type, entity_id), INDEX IDX_47C4FAD6E3E73126 (uploaded_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE audit_logs (id BIGINT AUTO_INCREMENT NOT NULL, entity_class VARCHAR(120) NOT NULL, entity_id VARCHAR(60) NOT NULL, action VARCHAR(20) NOT NULL, changes JSON DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, organization_id BIGINT DEFAULT NULL, user_id BIGINT DEFAULT NULL, INDEX idx_audit_org_created (organization_id, created_at), INDEX idx_audit_entity (entity_class, entity_id), INDEX idx_audit_user (user_id), INDEX IDX_D62F285832C8A3DE (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE email_verification_tokens (id BIGINT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, user_id BIGINT NOT NULL, UNIQUE INDEX UNIQ_C81CA2ACB3BC57DA (token_hash), INDEX idx_email_verification_user (user_id), INDEX idx_email_verification_expires (expires_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE expenses (id BIGINT AUTO_INCREMENT NOT NULL, occurred_at DATE NOT NULL, category VARCHAR(30) NOT NULL, description VARCHAR(500) NOT NULL, amount NUMERIC(10, 2) NOT NULL, recurring TINYINT DEFAULT 0 NOT NULL, recurring_period VARCHAR(20) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, organization_id BIGINT NOT NULL, vehicle_id BIGINT NOT NULL, INDEX idx_expenses_org (organization_id), INDEX idx_expenses_vehicle_date (vehicle_id, occurred_at), INDEX IDX_2496F35B545317D1 (vehicle_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mail_settings (id BIGINT AUTO_INCREMENT NOT NULL, host VARCHAR(255) NOT NULL, port INT NOT NULL, encryption VARCHAR(10) NOT NULL, username VARCHAR(255) DEFAULT NULL, password_cipher LONGTEXT DEFAULT NULL, from_address VARCHAR(180) NOT NULL, from_name VARCHAR(100) DEFAULT NULL, enabled TINYINT DEFAULT 0 NOT NULL, updated_at DATETIME NOT NULL, updated_by_id BIGINT DEFAULT NULL, INDEX IDX_3892618D896DBBDE (updated_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE maintenances (id BIGINT AUTO_INCREMENT NOT NULL, performed_at DATE NOT NULL, km INT NOT NULL, type VARCHAR(30) NOT NULL, description LONGTEXT NOT NULL, cost NUMERIC(10, 2) DEFAULT NULL, workshop VARCHAR(200) DEFAULT NULL, category VARCHAR(20) DEFAULT \'scheduled\' NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, organization_id BIGINT NOT NULL, vehicle_id BIGINT NOT NULL, INDEX idx_maintenances_org (organization_id), INDEX idx_maintenances_vehicle_date (vehicle_id, performed_at), INDEX IDX_C2F7112F545317D1 (vehicle_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE organization_invitations (id BIGINT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, role VARCHAR(20) NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, organization_id BIGINT NOT NULL, invited_by BIGINT DEFAULT NULL, UNIQUE INDEX UNIQ_137BB4D5B3BC57DA (token_hash), INDEX idx_org_invitation_org (organization_id), INDEX idx_org_invitation_email (email), INDEX idx_org_invitation_expires (expires_at), INDEX IDX_137BB4D5421FF255 (invited_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE organization_members (id BIGINT AUTO_INCREMENT NOT NULL, role VARCHAR(20) DEFAULT \'member\' NOT NULL, accepted_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, organization_id BIGINT NOT NULL, user_id BIGINT NOT NULL, invited_by BIGINT DEFAULT NULL, INDEX idx_org_members_user (user_id), INDEX idx_org_members_org (organization_id), UNIQUE INDEX uniq_org_user (organization_id, user_id), INDEX IDX_88725ABC421FF255 (invited_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE organizations (id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, slug VARCHAR(80) NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, UNIQUE INDEX UNIQ_427C1C7F989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE password_reset_tokens (id BIGINT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, user_id BIGINT NOT NULL, UNIQUE INDEX UNIQ_3967A216B3BC57DA (token_hash), INDEX idx_password_reset_user (user_id), INDEX idx_password_reset_expires (expires_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE push_settings (id BIGINT AUTO_INCREMENT NOT NULL, vapid_public_key LONGTEXT NOT NULL, vapid_private_key_cipher LONGTEXT DEFAULT NULL, vapid_subject VARCHAR(255) NOT NULL, enabled TINYINT DEFAULT 0 NOT NULL, updated_at DATETIME NOT NULL, updated_by_id BIGINT DEFAULT NULL, INDEX IDX_81154D9F896DBBDE (updated_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE push_subscriptions (id BIGINT AUTO_INCREMENT NOT NULL, platform VARCHAR(10) NOT NULL, endpoint LONGTEXT DEFAULT NULL, p256dh LONGTEXT DEFAULT NULL, auth_secret LONGTEXT DEFAULT NULL, device_label VARCHAR(120) DEFAULT NULL, user_agent VARCHAR(500) DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, last_seen_at DATETIME DEFAULT NULL, user_id BIGINT NOT NULL, INDEX idx_push_user (user_id), INDEX idx_push_platform (platform), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE refresh_tokens (id BIGINT AUTO_INCREMENT NOT NULL, token VARCHAR(128) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, revoked_at DATETIME DEFAULT NULL, user_id BIGINT NOT NULL, UNIQUE INDEX UNIQ_9BACE7E15F37A13B (token), INDEX idx_refresh_tokens_user (user_id), INDEX idx_refresh_tokens_expires (expires_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE refuelings (id BIGINT AUTO_INCREMENT NOT NULL, refueled_at DATE NOT NULL, km INT NOT NULL, liters NUMERIC(8, 3) NOT NULL, price_per_liter NUMERIC(6, 4) NOT NULL, total_cost NUMERIC(10,2) GENERATED ALWAYS AS (liters * price_per_liter) STORED, fuel_type VARCHAR(20) NOT NULL, full_tank TINYINT DEFAULT 1 NOT NULL, station VARCHAR(200) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, organization_id BIGINT NOT NULL, vehicle_id BIGINT NOT NULL, INDEX idx_refuelings_org (organization_id), INDEX idx_refuelings_vehicle_date (vehicle_id, refueled_at), INDEX IDX_4C3A2868545317D1 (vehicle_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reminders (id BIGINT AUTO_INCREMENT NOT NULL, type VARCHAR(30) NOT NULL, description VARCHAR(500) NOT NULL, due_date DATE DEFAULT NULL, due_km INT DEFAULT NULL, notify_days_before INT DEFAULT 30 NOT NULL, completed_at DATETIME DEFAULT NULL, last_notified_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, organization_id BIGINT NOT NULL, vehicle_id BIGINT NOT NULL, INDEX idx_reminders_org (organization_id), INDEX idx_reminders_vehicle_active (vehicle_id, due_date), INDEX IDX_6D92B9D4545317D1 (vehicle_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id BIGINT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, locale VARCHAR(5) DEFAULT \'it\' NOT NULL, handedness VARCHAR(5) DEFAULT \'right\' NOT NULL, avatar_path VARCHAR(500) DEFAULT NULL, email_verified_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vehicle_shares (id BIGINT AUTO_INCREMENT NOT NULL, role VARCHAR(20) DEFAULT \'viewer\' NOT NULL, accepted_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, vehicle_id BIGINT NOT NULL, user_id BIGINT NOT NULL, invited_by BIGINT DEFAULT NULL, INDEX idx_vehicle_shares_vehicle (vehicle_id), INDEX idx_vehicle_shares_user (user_id), UNIQUE INDEX uniq_vehicle_user (vehicle_id, user_id), INDEX IDX_398170AF421FF255 (invited_by), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE vehicles (id BIGINT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, brand VARCHAR(100) NOT NULL, model VARCHAR(100) NOT NULL, year SMALLINT NOT NULL, license_plate VARCHAR(20) DEFAULT NULL, vin VARCHAR(17) DEFAULT NULL, type VARCHAR(20) DEFAULT \'car\' NOT NULL, fuel_type VARCHAR(20) NOT NULL, secondary_fuel_type VARCHAR(20) DEFAULT NULL, initial_km INT DEFAULT 0 NOT NULL, photo_path VARCHAR(500) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, archived_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, organization_id BIGINT NOT NULL, INDEX idx_vehicles_org (organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE attachments ADD CONSTRAINT FK_47C4FAD632C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE attachments ADD CONSTRAINT FK_47C4FAD6E3E73126 FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F285832C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_D62F2858A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_verification_tokens ADD CONSTRAINT FK_C81CA2ACA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE expenses ADD CONSTRAINT FK_2496F35B32C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE expenses ADD CONSTRAINT FK_2496F35B545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mail_settings ADD CONSTRAINT FK_3892618D896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE maintenances ADD CONSTRAINT FK_C2F7112F32C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE maintenances ADD CONSTRAINT FK_C2F7112F545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organization_invitations ADD CONSTRAINT FK_137BB4D532C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organization_invitations ADD CONSTRAINT FK_137BB4D5421FF255 FOREIGN KEY (invited_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE organization_members ADD CONSTRAINT FK_88725ABC32C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organization_members ADD CONSTRAINT FK_88725ABCA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organization_members ADD CONSTRAINT FK_88725ABC421FF255 FOREIGN KEY (invited_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT FK_3967A216A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE push_settings ADD CONSTRAINT FK_81154D9F896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE push_subscriptions ADD CONSTRAINT FK_3FEC449DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT FK_9BACE7E1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE refuelings ADD CONSTRAINT FK_4C3A286832C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE refuelings ADD CONSTRAINT FK_4C3A2868545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reminders ADD CONSTRAINT FK_6D92B9D432C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reminders ADD CONSTRAINT FK_6D92B9D4545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_shares ADD CONSTRAINT FK_398170AF545317D1 FOREIGN KEY (vehicle_id) REFERENCES vehicles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_shares ADD CONSTRAINT FK_398170AFA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE vehicle_shares ADD CONSTRAINT FK_398170AF421FF255 FOREIGN KEY (invited_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE vehicles ADD CONSTRAINT FK_1FCE69FA32C8A3DE FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attachments DROP FOREIGN KEY FK_47C4FAD632C8A3DE');
        $this->addSql('ALTER TABLE attachments DROP FOREIGN KEY FK_47C4FAD6E3E73126');
        $this->addSql('ALTER TABLE audit_logs DROP FOREIGN KEY FK_D62F285832C8A3DE');
        $this->addSql('ALTER TABLE audit_logs DROP FOREIGN KEY FK_D62F2858A76ED395');
        $this->addSql('ALTER TABLE email_verification_tokens DROP FOREIGN KEY FK_C81CA2ACA76ED395');
        $this->addSql('ALTER TABLE expenses DROP FOREIGN KEY FK_2496F35B32C8A3DE');
        $this->addSql('ALTER TABLE expenses DROP FOREIGN KEY FK_2496F35B545317D1');
        $this->addSql('ALTER TABLE mail_settings DROP FOREIGN KEY FK_3892618D896DBBDE');
        $this->addSql('ALTER TABLE maintenances DROP FOREIGN KEY FK_C2F7112F32C8A3DE');
        $this->addSql('ALTER TABLE maintenances DROP FOREIGN KEY FK_C2F7112F545317D1');
        $this->addSql('ALTER TABLE organization_invitations DROP FOREIGN KEY FK_137BB4D532C8A3DE');
        $this->addSql('ALTER TABLE organization_invitations DROP FOREIGN KEY FK_137BB4D5421FF255');
        $this->addSql('ALTER TABLE organization_members DROP FOREIGN KEY FK_88725ABC32C8A3DE');
        $this->addSql('ALTER TABLE organization_members DROP FOREIGN KEY FK_88725ABCA76ED395');
        $this->addSql('ALTER TABLE organization_members DROP FOREIGN KEY FK_88725ABC421FF255');
        $this->addSql('ALTER TABLE password_reset_tokens DROP FOREIGN KEY FK_3967A216A76ED395');
        $this->addSql('ALTER TABLE push_settings DROP FOREIGN KEY FK_81154D9F896DBBDE');
        $this->addSql('ALTER TABLE push_subscriptions DROP FOREIGN KEY FK_3FEC449DA76ED395');
        $this->addSql('ALTER TABLE refresh_tokens DROP FOREIGN KEY FK_9BACE7E1A76ED395');
        $this->addSql('ALTER TABLE refuelings DROP FOREIGN KEY FK_4C3A286832C8A3DE');
        $this->addSql('ALTER TABLE refuelings DROP FOREIGN KEY FK_4C3A2868545317D1');
        $this->addSql('ALTER TABLE reminders DROP FOREIGN KEY FK_6D92B9D432C8A3DE');
        $this->addSql('ALTER TABLE reminders DROP FOREIGN KEY FK_6D92B9D4545317D1');
        $this->addSql('ALTER TABLE vehicle_shares DROP FOREIGN KEY FK_398170AF545317D1');
        $this->addSql('ALTER TABLE vehicle_shares DROP FOREIGN KEY FK_398170AFA76ED395');
        $this->addSql('ALTER TABLE vehicle_shares DROP FOREIGN KEY FK_398170AF421FF255');
        $this->addSql('ALTER TABLE vehicles DROP FOREIGN KEY FK_1FCE69FA32C8A3DE');
        $this->addSql('DROP TABLE attachments');
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE email_verification_tokens');
        $this->addSql('DROP TABLE expenses');
        $this->addSql('DROP TABLE mail_settings');
        $this->addSql('DROP TABLE maintenances');
        $this->addSql('DROP TABLE organization_invitations');
        $this->addSql('DROP TABLE organization_members');
        $this->addSql('DROP TABLE organizations');
        $this->addSql('DROP TABLE password_reset_tokens');
        $this->addSql('DROP TABLE push_settings');
        $this->addSql('DROP TABLE push_subscriptions');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE refuelings');
        $this->addSql('DROP TABLE reminders');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE vehicle_shares');
        $this->addSql('DROP TABLE vehicles');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
