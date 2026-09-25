<?php

declare(strict_types=1);

namespace App\Service\Push;

use App\Entity\PushSubscription;

/**
 * Astrae l'invio di una notifica push verso una singola subscription.
 *
 * Implementazioni:
 * - {@see WebPushNotifier}      → Web Push API + VAPID per browser (prod)
 * - {@see FakePushNotifier}     → dev/test, logga via LoggerInterface
 *
 * Il consumer normale è {@see PushDispatcher}, che instrada in base al `platform`
 * della subscription. Singoli notifier vengono usati solo internamente da lui.
 */
interface PushNotifierInterface
{
    /**
     * Quali platform sa servire questo notifier (es. ['web']).
     * Il PushDispatcher lo usa per costruire il routing.
     *
     * @return list<\App\Enum\PushPlatform>
     */
    public function supportedPlatforms(): array;

    public function send(PushSubscription $subscription, PushPayload $payload): PushDeliveryResult;
}
