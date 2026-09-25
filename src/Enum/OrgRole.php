<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Ruolo dell'utente dentro un'Organization.
 * - OWNER: pieno controllo, unico che può cancellare l'org
 * - ADMIN: gestisce membri, veicoli, settings; non può cancellare org
 * - MEMBER: accesso ai veicoli solo via VehicleShare esplicito
 */
enum OrgRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MEMBER = 'member';

    public function canManageOrg(): bool
    {
        return $this === self::OWNER;
    }

    public function canManageMembers(): bool
    {
        return $this === self::OWNER || $this === self::ADMIN;
    }

    public function isOrgAdmin(): bool
    {
        return $this === self::OWNER || $this === self::ADMIN;
    }

    /**
     * Mappa il ruolo al corrispettivo Symfony ROLE_* per la gerarchia security.
     */
    public function toSymfonyRole(): string
    {
        return match ($this) {
            self::OWNER => 'ROLE_ORG_OWNER',
            self::ADMIN => 'ROLE_ORG_ADMIN',
            self::MEMBER => 'ROLE_ORG_MEMBER',
        };
    }
}
