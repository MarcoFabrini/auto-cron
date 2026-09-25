<?php

declare(strict_types=1);

namespace App\Entity\Concern;

use App\Entity\Organization;

/**
 * Marker per entità tracciate dall'audit log (#3.6).
 * Doctrine event subscriber filtra via instanceof.
 *
 * getAuditOrganization() ritorna l'org owner (multi-tenant) per filtrare i log
 * per active organization. Ritorna null se l'entità è cross-org (es. User stesso).
 */
interface Auditable
{
    public function getAuditOrganization(): ?Organization;
}
