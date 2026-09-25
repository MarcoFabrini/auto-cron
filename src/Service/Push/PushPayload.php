<?php

declare(strict_types=1);

namespace App\Service\Push;

/**
 * Payload immutabile per una notifica push.
 * {@see WebPushNotifier} lo traduce nel formato Web Push nativo.
 */
final readonly class PushPayload
{
    public function __construct(
        public string $title,
        public string $body,
        /** @var array<string, scalar|null> */
        public array $data = [],
        public ?string $url = null,          // deep-link / web URL da aprire al tap
        public ?string $sound = 'default',
        public ?int $badge = null,
    ) {
    }
}
