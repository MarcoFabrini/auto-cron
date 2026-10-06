<?php

declare(strict_types=1);

namespace App\Service\Push;

use App\Entity\PushSubscription;
use App\Enum\PushPlatform;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * In dev e test non vogliamo davvero chiamare Apple/Google/Mozilla:
 * loggiamo solo cosa avremmo inviato. Questa classe viene aliasata
 * a PushNotifierInterface nei profili `dev` e `test` da services.yaml.
 *
 * `#[When]` con più env non è supportato dalla single-attribute syntax,
 * quindi marchiamo solo `dev` qui; per `test` lo alias di services.yaml
 * basta (l'istanza prod non viene mai registrata in test).
 */
#[When('dev')]
#[When('test')]
final class FakePushNotifier implements PushNotifierInterface
{
    /** @var list<array{userId: int|null, payload: PushPayload}> ricordati per i test, che li leggono con sent() */
    private array $sent = [];

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /** @return list<array{userId: int|null, payload: PushPayload}> */
    public function sent(): array
    {
        return $this->sent;
    }

    public function supportedPlatforms(): array
    {
        return [PushPlatform::WEB];
    }

    public function send(PushSubscription $subscription, PushPayload $payload): PushDeliveryResult
    {
        $this->sent[] = ['userId' => $subscription->getUser()->getId(), 'payload' => $payload];
        $this->logger->info('[FakePushNotifier] would send push', [
            'user_id' => $subscription->getUser()->getId(),
            'platform' => $subscription->getPlatform()->value,
            'device' => $subscription->getDeviceLabel(),
            'title' => $payload->title,
            'body' => $payload->body,
            'data' => $payload->data,
        ]);
        return PushDeliveryResult::ok();
    }
}
