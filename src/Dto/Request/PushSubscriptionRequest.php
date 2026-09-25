<?php

declare(strict_types=1);

namespace App\Dto\Request;

use App\Enum\PushPlatform;
use Symfony\Component\Validator\Constraints as Assert;

final class PushSubscriptionRequest
{
    public function __construct(
        public PushPlatform $platform = PushPlatform::WEB,

        // Web Push fields (all required)
        public ?string $endpoint = null,
        public ?string $p256dh = null,
        public ?string $authSecret = null,

        #[Assert\Length(max: 120)]
        public ?string $deviceLabel = null,
    ) {
    }
}
