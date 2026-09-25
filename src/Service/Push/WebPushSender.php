<?php

declare(strict_types=1);

namespace App\Service\Push;

use App\Entity\PushSubscription;
use App\Repository\PushSettingsRepository;
use App\Service\SecretCipher;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;

/**
 * Invio Web Push a basso livello. VAPID si configura solo da UI (Impostazioni
 * → Notifiche, owner) — nessun fallback via env. Riletta a ogni invio: nessuna
 * cache statica, così anche il worker long-running vede subito le modifiche.
 *
 * Usato da {@see WebPushNotifier} (dispatcher, solo prod) e dall'endpoint
 * "invia notifica di prova" (tutti gli env).
 */
final class WebPushSender
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly PushSettingsRepository $settingsRepo,
        private readonly SecretCipher $cipher,
    ) {
    }

    /** Public key attiva da esporre al frontend (DB se enabled+completa). '' se non configurata. */
    public function activePublicKey(): string
    {
        return $this->resolveVapid()['publicKey'] ?? '';
    }

    public function isConfigured(): bool
    {
        return $this->resolveVapid() !== null;
    }

    /** Da dove viene la config VAPID davvero usata per l'invio: 'db' | 'none'. */
    public function activeSource(): string
    {
        return $this->resolveVapid()['source'] ?? 'none';
    }

    public function send(PushSubscription $subscription, PushPayload $payload): PushDeliveryResult
    {
        $webPush = $this->buildClient();
        if ($webPush === null) {
            return PushDeliveryResult::failed('vapid_not_configured');
        }

        $endpoint = $subscription->getEndpoint();
        $p256dh = $subscription->getP256dh();
        $auth = $subscription->getAuthSecret();
        if ($endpoint === null || $p256dh === null || $auth === null) {
            return PushDeliveryResult::failed('missing_web_push_fields');
        }

        try {
            $subObj = Subscription::create([
                'endpoint' => $endpoint,
                'publicKey' => $p256dh,
                'authToken' => $auth,
            ]);
        } catch (\Throwable $e) {
            return PushDeliveryResult::failed('invalid_subscription: '.$e->getMessage());
        }

        $body = json_encode(array_filter([
            'title' => $payload->title,
            'body' => $payload->body,
            'data' => $payload->data,
            'url' => $payload->url,
        ], static fn ($v) => $v !== null), JSON_THROW_ON_ERROR);

        try {
            $report = $webPush->sendOneNotification($subObj, $body, ['TTL' => 300]);
        } catch (\Throwable $e) {
            $this->logger->error('WebPush transport error', ['error' => $e->getMessage()]);
            return PushDeliveryResult::failed('transport: '.$e->getMessage());
        }

        if ($report->isSuccess()) {
            return PushDeliveryResult::ok();
        }

        $gone = $report->isSubscriptionExpired();
        $reason = $report->getReason() ?: 'unknown';
        $this->logger->warning('WebPush delivery failed', [
            'endpoint' => $endpoint,
            'gone' => $gone,
            'reason' => $reason,
        ]);

        return PushDeliveryResult::failed($reason, gone: $gone);
    }

    private function buildClient(): ?WebPush
    {
        $vapid = $this->resolveVapid();
        if ($vapid === null) {
            return null;
        }

        try {
            return new WebPush(['VAPID' => $vapid]);
        } catch (\Throwable $e) {
            $this->logger->error('WebPush init failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * @return array{source: string, subject: string, publicKey: string, privateKey: string}|null
     */
    private function resolveVapid(): ?array
    {
        $settings = $this->settingsRepo->get();
        if ($settings === null || !$settings->isEnabled() || !$settings->isComplete()) {
            return null;
        }

        try {
            $privateKey = $this->cipher->decrypt((string) $settings->getPrivateKeyCipher());
        } catch (\RuntimeException $e) {
            $this->logger->error('PushSettings private key decrypt failed', ['error' => $e->getMessage()]);

            return null;
        }

        return [
            'source' => 'db',
            'subject' => $settings->getSubject() !== '' ? $settings->getSubject() : 'mailto:admin@example.com',
            'publicKey' => $settings->getPublicKey(),
            'privateKey' => $privateKey,
        ];
    }
}
