<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\User;
use App\Service\RefreshRejection;
use App\Service\RefreshTokenService;
use App\Tests\Factory\UserFactory;
use App\Tests\Support\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\AbstractLogger;
use Symfony\Component\BrowserKit\Cookie;

/**
 * Rilevamento del riuso dei refresh token: un token già ruotato che ricompare FUORI dalla finestra di
 * tolleranza revoca tutta la sua famiglia (la sessione); DENTRO la finestra è solo una corsa tra richieste
 * legittime. Logout, scadenza e revoca per altro motivo restano un normale 401.
 */
final class RefreshTokenReuseTest extends ApiTestCase
{
    // -------------------- famiglia --------------------

    public function testLoginOpensAFamilyAndRotationInheritsIt(): void
    {
        $user = UserFactory::createOne(['email' => 'family@test.it']);
        $first = $this->login('family@test.it');
        $second = $this->refresh($first);
        self::assertResponseIsSuccessful();
        $third = $this->refresh($second);
        self::assertResponseIsSuccessful();

        $families = array_map(fn (string $t) => $this->row($t)['family_id'], [$first, $second, $third]);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $families[0]);
        self::assertSame([$families[0], $families[0]], [$families[1], $families[2]], 'La rotazione resta nella famiglia del login');
        self::assertNotNull($this->row($first)['rotated_at'], 'Il token sostituito è segnato come ruotato');
        self::assertNotNull($this->row($second)['rotated_at']);
        self::assertNull($this->row($third)['rotated_at']);
        self::assertNull($this->row($third)['revoked_at']);

        // Un secondo login (un altro dispositivo) è una famiglia a sé
        $other = $this->login('family@test.it');
        self::assertNotSame($families[0], $this->row($other)['family_id']);
        self::assertSame($user->getId(), (int) $this->row($other)['user_id']);
    }

    public function testPasswordChangeStartsAFreshFamily(): void
    {
        [$user, , $access] = $this->createAuthenticatedUser();
        $before = $this->issue($user);

        $this->jsonRequest('PUT', '/api/auth/password', [
            'currentPassword' => UserFactory::DEFAULT_PASSWORD,
            'newPassword' => 'Another-Pass-123',
        ], accessToken: $access);
        self::assertResponseIsSuccessful();

        $this->em()->clear();
        $live = $this->em()->getConnection()->fetchAllAssociative('SELECT family_id FROM refresh_tokens WHERE user_id = ? AND revoked_at IS NULL', [$user->getId()]);
        self::assertCount(1, $live);
        self::assertNotSame($this->row($before)['family_id'], $live[0]['family_id']);
        self::assertNull($this->row($before)['rotated_at'], 'Revocato per il cambio password, non ruotato');
    }

    // -------------------- dentro la finestra di tolleranza --------------------

    public function testReplayInsideTheGraceWindowIs401AndTheNewTokenKeepsWorking(): void
    {
        UserFactory::createOne(['email' => 'race@test.it']);
        $old = $this->login('race@test.it');
        $new = $this->refresh($old);
        self::assertResponseIsSuccessful();

        // Seconda scheda / retry: stesso token pochi istanti dopo
        $this->jsonRequest('POST', '/api/auth/refresh', ['refreshToken' => $old]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('auth.invalid_refresh_token', $this->jsonBody()['title']);
        self::assertNull($this->row($new)['revoked_at'], 'La corsa persa non tocca la famiglia');

        $this->refresh($new);
        self::assertResponseIsSuccessful();
    }

    public function testRaceLostOnTheWebDoesNotClearTheCookieJustDeliveredByTheWinner(): void
    {
        UserFactory::createOne(['email' => 'tabs@test.it']);
        $old = $this->login('tabs@test.it');
        $this->refresh($old);

        $this->client->getCookieJar()->set(new Cookie(RefreshTokenService::COOKIE_NAME, $old, path: '/api/auth'));
        $this->jsonRequest('POST', '/api/auth/refresh', null, clientType: 'web');

        self::assertResponseStatusCodeSame(401);
        self::assertSame([], $this->client->getResponse()->headers->getCookies(), 'Cancellare il cookie scollegherebbe l utente che ha appena ricevuto quello nuovo');
    }

    // -------------------- fuori dalla finestra: riuso --------------------

    public function testReplayOutsideTheGraceWindowRevokesTheWholeFamilyButNotOtherSessions(): void
    {
        $user = UserFactory::createOne(['email' => 'theft@test.it']);
        $stolen = $this->login('theft@test.it');
        $legit = $this->refresh($stolen);          // la vittima ruota
        $legitLatest = $this->refresh($legit);     // e continua a usare la sessione
        $otherDevice = $this->login('theft@test.it');
        $this->ageRotation($stolen, RefreshTokenService::REUSE_GRACE_SECONDS + 5);

        $this->jsonRequest('POST', '/api/auth/refresh', ['refreshToken' => $stolen]);

        // Stessa risposta pubblica di qualsiasi altro rifiuto: niente da dedurre per chi lo manda
        self::assertResponseStatusCodeSame(401);
        self::assertSame(
            ['type' => 'about:blank', 'title' => 'auth.invalid_refresh_token', 'status' => 401],
            $this->jsonBody(),
        );
        self::assertNotNull($this->row($legitLatest)['revoked_at'], 'Il token vivo della famiglia è revocato (sessione intera)');
        self::assertNull($this->row($legitLatest)['rotated_at'], 'Revocato dalla famiglia, non ruotato');

        $this->refresh($legitLatest);
        self::assertResponseStatusCodeSame(401);

        // Un altro dispositivo dello stesso utente non c'entra
        self::assertNull($this->row($otherDevice)['revoked_at']);
        $this->refresh($otherDevice);
        self::assertResponseIsSuccessful();
        self::assertSame($user->getId(), (int) $this->row($otherDevice)['user_id']);
    }

    public function testReuseOnTheWebClearsTheCookie(): void
    {
        UserFactory::createOne(['email' => 'cookie@test.it']);
        $stolen = $this->login('cookie@test.it');
        $this->refresh($stolen);
        $this->ageRotation($stolen, 60);

        $this->client->getCookieJar()->set(new Cookie(RefreshTokenService::COOKIE_NAME, $stolen, path: '/api/auth'));
        $this->jsonRequest('POST', '/api/auth/refresh', null, clientType: 'web');

        self::assertResponseStatusCodeSame(401);
        $cleared = array_filter(
            $this->client->getResponse()->headers->getCookies(),
            static fn ($c) => $c->getName() === RefreshTokenService::COOKIE_NAME && $c->getExpiresTime() < time(),
        );
        self::assertCount(1, $cleared);
        self::assertSame(0, $this->liveTokens('cookie@test.it'), 'Anche sul canale web la famiglia è revocata');
    }

    public function testReuseKeepsTheActiveOrganizationRulesOfRefreshForLegitSessions(): void
    {
        // Un riuso rilevato su un'altra famiglia non cambia il comportamento di /refresh per chi non c'entra
        UserFactory::createOne(['email' => 'bystander@test.it']);
        $mine = $this->login('bystander@test.it');
        $stolen = $this->login('bystander@test.it');
        $this->refresh($stolen);
        $this->ageRotation($stolen, 60);
        $this->jsonRequest('POST', '/api/auth/refresh', ['refreshToken' => $stolen]);
        self::assertResponseStatusCodeSame(401);

        $this->refresh($mine);

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('access_token', $this->jsonBody());
    }

    // -------------------- ciò che NON è riuso --------------------

    public function testLoggedOutTokenReplayIsAPlain401WithoutRevokingAnything(): void
    {
        UserFactory::createOne(['email' => 'logout@test.it']);
        $session = $this->login('logout@test.it');
        $other = $this->login('logout@test.it');
        $this->jsonRequest('POST', '/api/auth/logout', ['refresh_token' => $session]);
        self::assertResponseStatusCodeSame(204);
        // Anche a distanza di tempo: un token revocato per logout non è un token ruotato
        $this->em()->getConnection()->executeStatement('UPDATE refresh_tokens SET revoked_at = ? WHERE token = ?', [(new \DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s'), RefreshTokenService::hash($session)]);

        $this->jsonRequest('POST', '/api/auth/refresh', ['refreshToken' => $session]);

        self::assertResponseStatusCodeSame(401);
        self::assertNull($this->row($session)['rotated_at']);
        self::assertSame(1, $this->liveTokens('logout@test.it'), 'L altra sessione resta viva');
        $this->refresh($other);
        self::assertResponseIsSuccessful();
    }

    public function testExpiredRotatedTokenIsAPlain401WithoutSideEffects(): void
    {
        UserFactory::createOne(['email' => 'expired@test.it']);
        $old = $this->login('expired@test.it');
        $current = $this->refresh($old);
        $this->ageRotation($old, 3600);
        $this->em()->getConnection()->executeStatement('UPDATE refresh_tokens SET expires_at = ? WHERE token = ?', [(new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s'), RefreshTokenService::hash($old)]);

        $this->jsonRequest('POST', '/api/auth/refresh', ['refreshToken' => $old]);

        self::assertResponseStatusCodeSame(401);
        self::assertNull($this->row($current)['revoked_at'], 'Un token scaduto non può più fare danni: non vale come riuso');
    }

    public function testUnknownTokenIsAPlain401(): void
    {
        UserFactory::createOne(['email' => 'unknown@test.it']);
        $session = $this->login('unknown@test.it');

        $this->jsonRequest('POST', '/api/auth/refresh', ['refreshToken' => str_repeat('a', 96)]);

        self::assertResponseStatusCodeSame(401);
        self::assertNull($this->row($session)['revoked_at']);
    }

    // -------------------- servizio: esito e log --------------------

    public function testReuseIsLoggedAtWarningWithIdsAndNeverTheToken(): void
    {
        $user = UserFactory::createOne();
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string|\Stringable, context: array<mixed>}> */
            public array $records = [];

            public function count(): int
            {
                return \count($this->records);
            }

            /** @return array{level: mixed, message: string|\Stringable, context: array<mixed>}|null */
            public function last(): ?array
            {
                return $this->records[\count($this->records) - 1] ?? null;
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
            }
        };
        $service = new RefreshTokenService($this->em(), static::getContainer()->get(\App\Repository\RefreshTokenRepository::class), $logger);
        $stolen = $service->issue($user);
        $plain = $stolen->getPlainToken();
        $current = $service->rotate($stolen);
        self::assertNotNull($current);

        // Dentro la finestra: nessun effetto, nessun log
        self::assertSame(RefreshRejection::RaceLost, $service->classifyRejected($plain));
        self::assertSame(0, $logger->count());
        self::assertSame(RefreshRejection::Invalid, $service->classifyRejected('mai-emesso'));
        self::assertSame(0, $logger->count());

        $this->ageRotation($plain, 60);
        self::assertSame(RefreshRejection::Reuse, $service->classifyRejected($plain));

        self::assertSame(1, $logger->count());
        $record = $logger->last();
        self::assertNotNull($record);
        self::assertSame('warning', $record['level']);
        self::assertSame(['user_id' => $user->getId(), 'family_id' => $stolen->getFamilyId(), 'revoked_tokens' => 1], $record['context']);
        self::assertStringNotContainsString($plain, json_encode($record, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString(RefreshTokenService::hash($plain), json_encode($record, JSON_THROW_ON_ERROR));
    }

    // -------------------- helpers --------------------

    private function login(string $email): string
    {
        $this->jsonRequest('POST', '/api/auth/login', ['email' => $email, 'password' => UserFactory::DEFAULT_PASSWORD]);
        self::assertResponseIsSuccessful();

        return (string) $this->jsonBody()['refresh_token'];
    }

    /** Presenta il token a /refresh (canale mobile) e ritorna quello nuovo, se la risposta lo contiene. */
    private function refresh(string $token): string
    {
        $this->jsonRequest('POST', '/api/auth/refresh', ['refreshToken' => $token]);

        return (string) ($this->jsonBody()['refresh_token'] ?? '');
    }

    private function issue(User $user): string
    {
        return static::getContainer()->get(RefreshTokenService::class)->issue($user)->getPlainToken();
    }

    /** @return array<string, mixed> */
    private function row(string $plain): array
    {
        $this->em()->clear();
        $row = $this->em()->getConnection()->fetchAssociative('SELECT * FROM refresh_tokens WHERE token = ?', [RefreshTokenService::hash($plain)]);
        self::assertIsArray($row, 'token non trovato');

        return $row;
    }

    private function liveTokens(string $email): int
    {
        return (int) $this->em()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM refresh_tokens rt JOIN users u ON u.id = rt.user_id WHERE u.email = ? AND rt.revoked_at IS NULL',
            [$email],
        );
    }

    /** Sposta indietro la data di rotazione: il test non aspetta davvero la finestra di tolleranza. */
    private function ageRotation(string $plain, int $seconds): void
    {
        $this->em()->getConnection()->executeStatement(
            'UPDATE refresh_tokens SET rotated_at = ? WHERE token = ?',
            [(new \DateTimeImmutable("-$seconds seconds"))->format('Y-m-d H:i:s'), RefreshTokenService::hash($plain)],
        );
        $this->em()->clear();
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
