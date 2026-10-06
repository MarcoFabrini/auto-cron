<?php

declare(strict_types=1);

namespace App\Dto\Request;

use App\Enum\PushPlatform;
use App\Service\Push\PushEndpointPolicy;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class PushSubscriptionRequest
{
    public function __construct(
        public PushPlatform $platform = PushPlatform::WEB,

        // Web Push fields (all required)
        #[Assert\Length(max: 2048)]
        public ?string $endpoint = null,
        #[Assert\Length(max: 255)]
        public ?string $p256dh = null,
        #[Assert\Length(max: 255)]
        public ?string $authSecret = null,

        #[Assert\Length(max: 120)]
        public ?string $deviceLabel = null,
    ) {
    }

    /** Il server fa una POST verso `endpoint`: solo i push service noti (vedi {@see PushEndpointPolicy}). */
    #[Assert\Callback]
    public function validateEndpointHost(ExecutionContextInterface $context): void
    {
        if ($this->endpoint === null || $this->endpoint === '') {
            return;
        }

        if (!PushEndpointPolicy::isAllowed($this->endpoint)) {
            $context->buildViolation('push.endpoint_not_allowed')->atPath('endpoint')->addViolation();
        }
    }
}
