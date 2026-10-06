<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Cambio di ruolo rifiutato: porta la chiave i18n (`title` del Problem Details) e lo status HTTP,
 * che il controller mappa con `problem()`. Le chiavi vivono sotto `errors.member.*` nei locali.
 */
final class MemberRoleChangeException extends \RuntimeException
{
    public const CANNOT_CHANGE_OWNER_ROLE = 'member.cannot_change_owner_role';
    public const CANNOT_CHANGE_ADMIN_ROLE = 'member.cannot_change_admin_role';
    public const CANNOT_CHANGE_OWN_ROLE = 'member.cannot_change_own_role';
    public const OWNER_ROLE_FORBIDDEN = 'member.owner_role_forbidden';
    public const LAST_OWNER = 'member.last_owner';
    /** L'attore ha perso il diritto di gestire i membri tra il voter e il lock (stessa chiave del voter). */
    public const FORBIDDEN = 'http.403';

    public function __construct(
        public readonly string $key,
        public readonly int $status,
    ) {
        parent::__construct($key);
    }

    public static function forbidden(string $key): self
    {
        return new self($key, 403);
    }

    public static function lastOwner(): self
    {
        return new self(self::LAST_OWNER, 409);
    }
}
