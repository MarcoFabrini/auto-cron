<?php

declare(strict_types=1);

namespace App\Dto\Request;

use App\Enum\PushPlatform;
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

    /**
     * Il server fa una POST verso `endpoint`: accettiamo solo https verso i push service noti,
     * altrimenti un utente potrebbe puntare il server a host della rete interna (SSRF cieco).
     */
    private const ALLOWED_HOST_SUFFIXES = [
        'googleapis.com',
        'push.services.mozilla.com',
        'notify.windows.com',
        'push.apple.com',
    ];

    #[Assert\Callback]
    public function validateEndpointHost(ExecutionContextInterface $context): void
    {
        if ($this->endpoint === null || $this->endpoint === '') {
            return;
        }

        $parts = parse_url($this->endpoint);
        $host = is_array($parts) && isset($parts['host']) ? strtolower($parts['host']) : '';
        $valid = is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && !isset($parts['user'], $parts['pass'])
            && (($parts['port'] ?? 443) === 443)
            && $host !== '';

        if ($valid) {
            $valid = false;
            foreach (self::ALLOWED_HOST_SUFFIXES as $suffix) {
                if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                    $valid = true;
                    break;
                }
            }
        }

        if (!$valid) {
            $context->buildViolation('push.endpoint_not_allowed')->atPath('endpoint')->addViolation();
        }
    }
}
