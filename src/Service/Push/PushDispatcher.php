<?php

declare(strict_types=1);

namespace App\Service\Push;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Enum\PushPlatform;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Sceglie a runtime quale {@see PushNotifierInterface} usare per ogni subscription
 * in base al campo `platform`. Si occupa anche del cleanup automatico dei token
 * scaduti (DeviceNotRegistered → rimuove la subscription).
 *
 * In dev/test il dispatch è servito da FakePushNotifier (#[When] dev/test); in prod
 * da WebPushNotifier. L'astrazione resta estendibile a nuove platform in futuro.
 *
 * Iniezione: `#[AutowireIterator]` raccoglie automaticamente tutti i servizi che
 * implementano PushNotifierInterface, rispettando i tag #[When] per environment.
 */
final class PushDispatcher
{
    /** @var array<string, PushNotifierInterface> map "platform.value" → notifier */
    private array $byPlatform = [];

    /**
     * @param iterable<PushNotifierInterface> $notifiers
     */
    public function __construct(
        #[AutowireIterator('app.push_notifier')] iterable $notifiers,
        private readonly PushSubscriptionRepository $subRepo,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
        foreach ($notifiers as $notifier) {
            foreach ($notifier->supportedPlatforms() as $platform) {
                // Se più notifier supportano la stessa platform, l'ultimo registrato vince
                // (consente sovrascrittura via alias dev/test in services.yaml)
                $this->byPlatform[$platform->value] = $notifier;
            }
        }
    }

    /**
     * Invia la stessa notifica a tutti i device dell'utente, su tutte le platform.
     * Risultato: numero di delivery riuscite.
     */
    public function notifyUser(User $user, PushPayload $payload): int
    {
        $subscriptions = $this->subRepo->findActiveForUser($user);
        $ok = 0;

        foreach ($subscriptions as $sub) {
            if ($this->sendToSubscription($sub, $payload)->success) {
                $ok++;
            }
        }

        return $ok;
    }

    public function sendToSubscription(PushSubscription $sub, PushPayload $payload): PushDeliveryResult
    {
        $notifier = $this->byPlatform[$sub->getPlatform()->value] ?? null;
        if (!$notifier) {
            $this->logger->error('PushDispatcher: no notifier for platform', [
                'platform' => $sub->getPlatform()->value,
            ]);
            return PushDeliveryResult::failed('no_notifier_for_platform');
        }

        $result = $notifier->send($sub, $payload);

        if ($result->gone) {
            // Il token è morto lato Apple/Google/browser: rimuoviamolo
            $this->em->remove($sub);
            $this->em->flush();
            $this->logger->info('PushDispatcher: removed dead subscription', [
                'subscription_id' => $sub->getId(),
                'platform' => $sub->getPlatform()->value,
            ]);
        }

        return $result;
    }
}
