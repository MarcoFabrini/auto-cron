<?php

declare(strict_types=1);

namespace App\Dto\Request;

use App\Enum\OrgRole;

final class MemberRoleRequest
{
    public function __construct(
        public OrgRole $role,
    ) {
    }
}
