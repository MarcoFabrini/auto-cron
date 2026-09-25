<?php

declare(strict_types=1);

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public string $email = '',

        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 200)]
        public string $password = '',

        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $firstName = '',

        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $lastName = '',

        #[Assert\Choice(choices: ['it', 'en'])]
        public string $locale = 'it',

        /** Nome dell'Organization personale creata al register. Default: "{firstName}'s workspace". */
        #[Assert\Length(max: 150)]
        public ?string $organizationName = null,
    ) {
    }
}
