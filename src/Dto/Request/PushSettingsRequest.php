<?php

declare(strict_types=1);

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class PushSettingsRequest
{
    public function __construct(
        // Soggetto VAPID: mailto: o https URL del responsabile (richiesto dallo standard).
        #[Assert\Length(max: 255)]
        public ?string $subject = null,

        public bool $enabled = false,
    ) {
    }
}
