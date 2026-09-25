<?php

declare(strict_types=1);

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class AcceptInvitationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        public string $token = '',
    ) {
    }
}
