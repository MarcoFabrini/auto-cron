<?php

declare(strict_types=1);

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class OrganizationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 150)]
        public string $name = '',

        #[Assert\Length(max: 80)]
        #[Assert\Regex(pattern: '/^[a-z0-9-]*$/', message: 'org.slug_format')]
        public ?string $slug = null,
    ) {
    }
}
