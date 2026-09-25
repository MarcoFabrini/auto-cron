<?php

declare(strict_types=1);

namespace App\Service\Push;

use App\Entity\PushSubscription;
use App\Enum\PushPlatform;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * Web Push (browser) per il dispatcher. Sottile wrapper su {@see WebPushSender},
 * che incapsula la sorgente VAPID (push_settings da UI con fallback env).
 *
 * Registrato solo in prod: in dev/test FakePushNotifier copre tutte le platform
 * per il dispatch automatico. L'invio reale (anche in dev) passa comunque da
 * WebPushSender quando l'utente preme "invia notifica di prova".
 */
#[When('prod')]
final class WebPushNotifier implements PushNotifierInterface
{
    public function __construct(
        private readonly WebPushSender $sender,
    ) {
    }

    public function supportedPlatforms(): array
    {
        return [PushPlatform::WEB];
    }

    public function send(PushSubscription $subscription, PushPayload $payload): PushDeliveryResult
    {
        return $this->sender->send($subscription, $payload);
    }
}
