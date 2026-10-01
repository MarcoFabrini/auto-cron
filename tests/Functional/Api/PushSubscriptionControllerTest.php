<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use App\Tests\Support\ApiTestCase;

final class PushSubscriptionControllerTest extends ApiTestCase
{
    public function testCreateWebSubscriptionRequiresAllWebFields(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        // Missing p256dh + authSecret
        $this->jsonRequest('POST', '/api/push-subscriptions', [
            'platform' => 'web',
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/123',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(400);
    }

    #[DataProvider('disallowedEndpoints')]
    public function testRejectsEndpointsOutsideKnownPushServices(string $endpoint): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/push-subscriptions', [
            'platform' => 'web',
            'endpoint' => $endpoint,
            'p256dh' => 'p256dh-key',
            'authSecret' => 'auth-secret',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function disallowedEndpoints(): iterable
    {
        yield 'metadata service' => ['http://169.254.169.254/latest/meta-data'];
        yield 'internal host' => ['https://redis:6379/'];
        yield 'plain http on allowed host' => ['http://fcm.googleapis.com/fcm/send/1'];
        yield 'lookalike suffix' => ['https://evilgoogleapis.com/x'];
        yield 'userinfo trick' => ['https://fcm.googleapis.com@evil.test/x'];
    }

    public function testCreateWebSubscriptionSucceeds(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/push-subscriptions', [
            'platform' => 'web',
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/123',
            'p256dh' => 'p256dh-key',
            'authSecret' => 'auth-secret',
            'deviceLabel' => 'Chrome on MacBook',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('web', $this->jsonBody()['platform']);
    }

    public function testUpsertSameEndpointDoesNotDuplicate(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        $payload = [
            'platform' => 'web',
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/repeat',
            'p256dh' => 'p256dh',
            'authSecret' => 'auth',
        ];

        $this->jsonRequest('POST', '/api/push-subscriptions', $payload, accessToken: $token);
        $this->jsonRequest('POST', '/api/push-subscriptions', $payload, accessToken: $token);

        $this->jsonRequest('GET', '/api/push-subscriptions', accessToken: $token);
        self::assertCount(1, $this->jsonBody(), 'Same endpoint should upsert, not duplicate');
    }

    public function testListReturnsOnlyOwnSubscriptions(): void
    {
        [, , $tokenA] = $this->createAuthenticatedUser();
        [, , $tokenB] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/push-subscriptions', [
            'platform' => 'web',
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/user-a',
            'p256dh' => 'p256dh',
            'authSecret' => 'auth',
        ], accessToken: $tokenA);

        $this->jsonRequest('GET', '/api/push-subscriptions', accessToken: $tokenB);
        self::assertCount(0, $this->jsonBody());
    }
}
