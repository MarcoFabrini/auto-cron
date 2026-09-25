<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\ApiTestCase;

final class PushSubscriptionControllerTest extends ApiTestCase
{
    public function testCreateWebSubscriptionRequiresAllWebFields(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        // Missing p256dh + authSecret
        $this->jsonRequest('POST', '/api/push-subscriptions', [
            'platform' => 'web',
            'endpoint' => 'https://example.com/push/123',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(400);
    }

    public function testCreateWebSubscriptionSucceeds(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/push-subscriptions', [
            'platform' => 'web',
            'endpoint' => 'https://example.com/push/123',
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
            'endpoint' => 'https://example.com/push/repeat',
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
            'endpoint' => 'https://example.com/push/user-a',
            'p256dh' => 'p256dh',
            'authSecret' => 'auth',
        ], accessToken: $tokenA);

        $this->jsonRequest('GET', '/api/push-subscriptions', accessToken: $tokenB);
        self::assertCount(0, $this->jsonBody());
    }
}
