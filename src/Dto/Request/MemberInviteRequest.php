<?php

declare(strict_types=1);

namespace App\Dto\Request;

use App\Enum\OrgRole;
use Symfony\Component\Validator\Constraints as Assert;

final class MemberInviteRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public string $email = '',

        public OrgRole $role = OrgRole::MEMBER,
    ) {
    }
}
