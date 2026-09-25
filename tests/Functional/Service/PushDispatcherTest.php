<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\PushSubscription;
use App\Enum\PushPlatform;
use App\Service\Push\FakePushNotifier;
use App\Service\Push\PushDispatcher;
use App\Service\Push\PushPayload;
use App\Service\Push\PushNotifierInterface;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * PushDispatcher routing + cleanup gone subscriptions (closes #1.7).
 *
 * In env test: FakePushNotifier copre la platform web (#[When('test')]).
 * Il vero WebPushNotifier è #[When('prod')] → non registrato qui.
 */
final class PushDispatcherTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    private PushDispatcher $dispatcher;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->dispatcher = static::getContainer()->get(PushDispatcher::class);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testFakeNotifierRegisteredForWebInTestEnv(): void
    {
        $fake = static::getContainer()->get(FakePushNotifier::class);
        self::assertSame([PushPlatform::WEB], $fake->supportedPlatforms());
    }

    public function testNotifyUserCallsFakeForWebSubscription(): void
    {
        $user = UserFactory::createOne();

        $sub = (new PushSubscription())
            ->setUser($user)
            ->setPlatform(PushPlatform::WEB)
            ->setEndpoint('https://fcm.example.com/abc')
            ->setP256dh('p256dh-key')
            ->setAuthSecret('auth-secret');
        $this->em->persist($sub);
        $this->em->flush();

        $sent = $this->dispatcher->notifyUser($user, new PushPayload(
            title: 'Test',
            body: 'Hello web',
        ));

        // FakePushNotifier ritorna sempre ok → 1 delivery success
        self::assertSame(1, $sent);
    }

    public function testNotifyUserHitsAllUserDevices(): void
    {
        $user = UserFactory::createOne();

        // 2 device web dello stesso utente
        $web1 = (new PushSubscription())
            ->setUser($user)->setPlatform(PushPlatform::WEB)
            ->setEndpoint('https://e.example/1')->setP256dh('k')->setAuthSecret('a');
        $web2 = (new PushSubscription())
            ->setUser($user)->setPlatform(PushPlatform::WEB)
            ->setEndpoint('https://e.example/2')->setP256dh('k')->setAuthSecret('a');

        foreach ([$web1, $web2] as $s) {
            $this->em->persist($s);
        }
        $this->em->flush();

        $sent = $this->dispatcher->notifyUser($user, new PushPayload(title: 'All', body: 'devices'));
        self::assertSame(2, $sent, 'Entrambi i device devono ricevere push');
    }

    public function testGoneResultRemovesSubscription(): void
    {
        // Stub notifier che ritorna gone=true → dispatcher rimuove sub
        $user = UserFactory::createOne();

        $sub = (new PushSubscription())
            ->setUser($user)
            ->setPlatform(PushPlatform::WEB)
            ->setEndpoint('https://dead.example/x')
            ->setP256dh('k')
            ->setAuthSecret('a');
        $this->em->persist($sub);
        $this->em->flush();
        $subId = $sub->getId();

        // Sostituisci dispatcher con uno che usa StubGoneNotifier
        $stub = new class implements PushNotifierInterface {
            public function supportedPlatforms(): array { return [PushPlatform::WEB]; }
            public function send(PushSubscription $s, PushPayload $p): \App\Service\Push\PushDeliveryResult
            {
                return \App\Service\Push\PushDeliveryResult::failed('expired', gone: true);
            }
        };

        $subRepo = static::getContainer()->get(\App\Repository\PushSubscriptionRepository::class);
        $logger = static::getContainer()->get('logger');

        $stubDispatcher = new PushDispatcher([$stub], $subRepo, $this->em, $logger);

        $result = $stubDispatcher->sendToSubscription($sub, new PushPayload(title: 'X', body: 'Y'));

        self::assertFalse($result->success);
        self::assertTrue($result->gone);
        self::assertNull($subRepo->find($subId), 'Sub gone deve essere rimossa dal DB');
    }
}
