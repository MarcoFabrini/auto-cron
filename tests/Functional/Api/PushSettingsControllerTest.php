<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Enum\OrgRole;
use App\Repository\PushSettingsRepository;
use App\Service\SecretCipher;
use App\Tests\Support\ApiTestCase;

final class PushSettingsControllerTest extends ApiTestCase
{
    public function testGetReturnsDefaultsWhenNeverConfigured(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('GET', '/api/settings/push', accessToken: $token);

        self::assertResponseIsSuccessful();
        $body = $this->jsonBody();
        self::assertFalse($body['enabled']);
        self::assertFalse($body['hasKeys']);
        self::assertSame('', $body['publicKey']);
    }

    public function testGetForbiddenForAdminAndMember(): void
    {
        // Un altro utente occupa lo slot di primo iscritto (= admin di istanza),
        // altrimenti l'ADMIN/MEMBER creato sotto sarebbe l'unico utente del test
        // e passerebbe la verifica per coincidenza.
        $this->createAuthenticatedUser();

        foreach ([OrgRole::ADMIN, OrgRole::MEMBER] as $role) {
            [, , $token] = $this->createAuthenticatedUser($role);

            $this->jsonRequest('GET', '/api/settings/push', accessToken: $token);
            self::assertResponseStatusCodeSame(403);
            self::assertSame('settings.instance_admin_required', $this->jsonBody()['title']);
        }
    }

    public function testGenerateForbiddenForMember(): void
    {
        $this->createAuthenticatedUser();
        [, , $token] = $this->createAuthenticatedUser(OrgRole::MEMBER);

        $this->jsonRequest('POST', '/api/settings/push/generate', accessToken: $token);
        self::assertResponseStatusCodeSame(403);
    }

    public function testForbiddenForOwnerOfAnotherOrganization(): void
    {
        // La falla reale: l'autorizzazione era "sei OWNER di UNA org", non "sei
        // l'admin di istanza". Ogni utente è OWNER della propria org personale — e
        // con la vecchia logica poteva rigenerare le chiavi VAPID dell'istanza
        // (rompendo il push di tutti). Il secondo OWNER (org diversa, non il primo
        // utente) resta fuori.
        $this->createAuthenticatedUser(); // primo utente = admin di istanza
        [, , $token] = $this->createAuthenticatedUser(); // secondo OWNER, org diversa

        $this->jsonRequest('GET', '/api/settings/push', accessToken: $token);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('settings.instance_admin_required', $this->jsonBody()['title']);

        $this->jsonRequest('POST', '/api/settings/push/generate', accessToken: $token);
        self::assertResponseStatusCodeSame(403);
    }

    public function testGenerateCreatesEncryptedKeypair(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/settings/push/generate', accessToken: $token);

        self::assertResponseIsSuccessful();
        $body = $this->jsonBody();
        self::assertNotSame('', $body['publicKey']);
        self::assertArrayHasKey('removedSubscriptions', $body);

        // GET ora riporta le chiavi presenti; la private non esce mai dall'API.
        $this->jsonRequest('GET', '/api/settings/push', accessToken: $token);
        $get = $this->jsonBody();
        self::assertTrue($get['hasKeys']);
        self::assertSame($body['publicKey'], $get['publicKey']);
        self::assertArrayNotHasKey('privateKey', $get);

        // At rest: cifrata (non plaintext), decifrabile con la chiave dell'app.
        $settings = static::getContainer()->get(PushSettingsRepository::class)->get();
        self::assertNotNull($settings);
        $cipher = static::getContainer()->get(SecretCipher::class);
        $privateKey = $cipher->decrypt((string) $settings->getPrivateKeyCipher());
        self::assertNotSame('', $privateKey);
        self::assertStringNotContainsString($privateKey, (string) $settings->getPrivateKeyCipher());
    }

    public function testEnableWithoutKeysReturns400(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('PUT', '/api/settings/push', [
            'subject' => 'mailto:admin@example.com',
            'enabled' => true,
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(400);
        self::assertSame('push.no_keys', $this->jsonBody()['title']);
    }

    public function testGenerateAutomaticallyEnablesAndExposesDbPublicKeyAtRuntime(): void
    {
        // Generare una coppia è un'azione esplicita dell'owner: attiva da sola,
        // niente secondo passaggio "abilita" separato (era un passaggio in più).
        // NB: env-indipendente — l'env VAPID_* può essere valorizzato (fallback);
        // qui verifichiamo solo che, una volta generata, la public key del DB vince.
        [, , $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/settings/push/generate', accessToken: $token);
        $publicKey = $this->jsonBody()['publicKey'];

        $this->jsonRequest('GET', '/api/settings/push', accessToken: $token);
        self::assertTrue($this->jsonBody()['enabled']);

        // La public key del DB (appena generata) è esposta al frontend a runtime.
        $this->jsonRequest('GET', '/api/push-subscriptions/vapid-public-key', accessToken: $token);
        self::assertSame($publicKey, $this->jsonBody()['publicKey']);

        // Una volta abilitata, la sorgente esposta in Settings è 'db' (a prescindere dall'env).
        $this->jsonRequest('GET', '/api/settings/push', accessToken: $token);
        self::assertSame('db', $this->jsonBody()['source']);
    }

    public function testTestReturns400WhenNoSubscription(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        // Generare abilita già da solo — config completa, ma nessun device sottoscritto.
        $this->jsonRequest('POST', '/api/settings/push/generate', accessToken: $token);

        $this->jsonRequest('POST', '/api/settings/push/test', accessToken: $token);

        self::assertResponseStatusCodeSame(400);
        self::assertSame('push.no_subscription', $this->jsonBody()['title']);
    }

    public function testEndpointsRequireAuth(): void
    {
        $this->jsonRequest('GET', '/api/settings/push');
        self::assertResponseStatusCodeSame(401);
        $this->jsonRequest('POST', '/api/settings/push/generate');
        self::assertResponseStatusCodeSame(401);
        $this->jsonRequest('POST', '/api/settings/push/test');
        self::assertResponseStatusCodeSame(401);
    }
}
