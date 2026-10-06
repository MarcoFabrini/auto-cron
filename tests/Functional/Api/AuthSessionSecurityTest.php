<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Enum\OrgRole;
use App\Service\RefreshTokenService;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\OrganizationMemberFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Support\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\BrowserKit\Cookie;

/**
 * Sessione e rate limit dei login/refresh: ciò che AuthControllerTest non copre (limiti di
 * tentativi, organizzazione attiva che sopravvive al refresh, token ruotati o scaduti).
 */
final class AuthSessionSecurityTest extends ApiTestCase
{
    // -------------------- rate limit --------------------

    public function testLoginIsBlockedAfterFiveAttemptsForTheSameEmailAndTheCorrectPasswordDoesNotBypassIt(): void
    {
        $this->keepRateLimiterCountersForTheWholeTest();
        UserFactory::createOne(['email' => 'victim@test.it']);
        UserFactory::createOne(['email' => 'other@test.it']);

        for ($i = 1; $i <= 5; ++$i) {
            $this->login('victim@test.it', 'wrong-password');
            self::assertResponseStatusCodeSame(401, "tentativo $i");
        }

        // 6° tentativo: limite raggiunto, anche con la password giusta (altrimenti il limite non frenerebbe il brute force)
        $this->login('victim@test.it', UserFactory::DEFAULT_PASSWORD);
        self::assertResponseStatusCodeSame(429);
        self::assertSame('auth.too_many_attempts', $this->jsonBody()['title']);
        self::assertArrayNotHasKey('access_token', $this->jsonBody());

        // Il contatore è per (email, IP): un altro account non è toccato
        $this->login('other@test.it', UserFactory::DEFAULT_PASSWORD);
        self::assertResponseIsSuccessful();
    }

    public function testLoginLimiterKeyIsCaseInsensitiveOnTheEmail(): void
    {
        $this->keepRateLimiterCountersForTheWholeTest();
        UserFactory::createOne(['email' => 'case@test.it']);

        for ($i = 0; $i < 5; ++$i) {
            $this->login(0 === $i % 2 ? 'case@test.it' : 'CASE@test.it', 'wrong-password');
        }

        $this->login('Case@Test.it', UserFactory::DEFAULT_PASSWORD);
        self::assertResponseStatusCodeSame(429, 'Cambiare le maiuscole non deve azzerare il contatore');
    }

    public function testRefreshIsRateLimitedPerIp(): void
    {
        $this->keepRateLimiterCountersForTheWholeTest();
        for ($i = 1; $i <= 30; ++$i) {
            $this->jsonRequest('POST', '/api/auth/refresh', ['refreshToken' => 'fake-'.$i]);
            self::assertResponseStatusCodeSame(401, "richiesta $i");
        }

        $this->jsonRequest('POST', '/api/auth/refresh', ['refreshToken' => 'fake-31']);
        self::assertResponseStatusCodeSame(429);
        self::assertSame('auth.too_many_attempts', $this->jsonBody()['title']);
    }

    // -------------------- refresh token --------------------

    public function testExpiredRefreshTokenIsRejected(): void
    {
        $user = UserFactory::createOne();
        $plain = $this->issueRefreshToken($user);
        $this->em()->getConnection()->executeStatement('UPDATE refresh_tokens SET expires_at = ?', ['2000-01-01 00:00:00']);
        $this->em()->clear(); // l'entità in memoria ha ancora la vecchia scadenza

        $this->jsonRequest('POST', '/api/auth/refresh', ['refreshToken' => $plain]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame('auth.invalid_refresh_token', $this->jsonBody()['title']);
    }

    public function testInvalidRefreshCookieIsClearedFromTheBrowser(): void
    {
        $this->client->getCookieJar()->set(new Cookie(RefreshTokenService::COOKIE_NAME, 'stale-cookie-value', path: '/api/auth'));

        $this->jsonRequest('POST', '/api/auth/refresh', null, clientType: 'web');

        self::assertResponseStatusCodeSame(401);
        $cleared = array_filter(
            $this->client->getResponse()->headers->getCookies(),
            static fn ($c) => $c->getName() === RefreshTokenService::COOKIE_NAME && $c->getExpiresTime() < time(),
        );
        self::assertCount(1, $cleared, 'Un cookie di sessione invalido va cancellato, non lasciato a ritentare all infinito');
    }

    public function testRotationSucceedsForExactlyOneOfTwoConcurrentRefreshes(): void
    {
        $user = UserFactory::createOne();
        $service = static::getContainer()->get(RefreshTokenService::class);
        $refresh = $service->issue($user);

        // Due richieste hanno letto lo stesso token valido: solo la prima lo ruota
        $first = $service->rotate($refresh);
        $second = $service->rotate($refresh);

        self::assertInstanceOf(RefreshToken::class, $first);
        self::assertNull($second, 'La seconda rotazione concorrente non deve emettere una seconda sessione');
        static::getContainer()->get(EntityManagerInterface::class)->clear(); // la revoca è un UPDATE DQL: rilegge dal DB
        self::assertNull($service->findValid($refresh->getPlainToken()), 'Il vecchio token è revocato');
        self::assertNotNull($service->findValid($first->getPlainToken()));
    }

    // -------------------- organizzazione attiva --------------------

    public function testSwitchOrgToUnknownOrganizationReturns404(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/auth/switch-org', ['organizationId' => 999999], accessToken: $token);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('org.not_found', $this->jsonBody()['title']);
    }

    public function testSwitchOrgRefusesAPendingMembership(): void
    {
        [$user, , $token] = $this->createAuthenticatedUser();
        $pending = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $pending, 'acceptedAt' => null]);

        $this->jsonRequest('POST', '/api/auth/switch-org', ['organizationId' => $pending->getId()], accessToken: $token);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('auth.not_member', $this->jsonBody()['title']);
    }

    public function testSwitchedOrganizationSurvivesTheRefresh(): void
    {
        $user = UserFactory::createOne(['email' => 'multi@test.it']);
        $first = OrganizationFactory::createOne();
        $second = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $first, 'role' => OrgRole::OWNER]);
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $second, 'role' => OrgRole::MEMBER]);

        $session = $this->login('multi@test.it', UserFactory::DEFAULT_PASSWORD);
        $this->jsonRequest('POST', '/api/auth/switch-org', [
            'organizationId' => $second->getId(),
            'refreshToken' => $session['refresh_token'],
        ], accessToken: $session['access_token']);
        self::assertResponseIsSuccessful();

        $this->jsonRequest('POST', '/api/auth/refresh', ['refreshToken' => $session['refresh_token']]);

        self::assertResponseIsSuccessful();
        self::assertSame($second->getId(), $this->activeOrgOf($this->jsonBody()['access_token']), 'Il refresh non deve riportare alla prima org');
    }

    public function testRefreshDropsTheSwitchedOrganizationOnceTheUserIsNoLongerMember(): void
    {
        $user = UserFactory::createOne(['email' => 'removed@test.it']);
        $first = OrganizationFactory::createOne();
        $second = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $first, 'role' => OrgRole::OWNER]);
        $membership = OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $second, 'role' => OrgRole::MEMBER]);

        $session = $this->login('removed@test.it', UserFactory::DEFAULT_PASSWORD);
        $this->jsonRequest('POST', '/api/auth/switch-org', [
            'organizationId' => $second->getId(),
            'refreshToken' => $session['refresh_token'],
        ], accessToken: $session['access_token']);
        self::assertResponseIsSuccessful();

        // L'utente viene rimosso dalla seconda org mentre la sessione la ricorda ancora
        $this->em()->getConnection()->executeStatement('DELETE FROM organization_members WHERE id = ?', [$membership->getId()]);
        $this->em()->clear();

        $this->jsonRequest('POST', '/api/auth/refresh', ['refreshToken' => $session['refresh_token']]);

        self::assertResponseIsSuccessful();
        self::assertNotSame($second->getId(), $this->activeOrgOf($this->jsonBody()['access_token']), 'Un token emesso per un\'org che non è più sua sarebbe un accesso residuo');
    }

    public function testSwitchOrgDoesNotMoveSomeoneElsesSession(): void
    {
        $victim = UserFactory::createOne(['email' => 'session-owner@test.it']);
        $victimOrg = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne(['user' => $victim, 'organization' => $victimOrg, 'role' => OrgRole::OWNER]);
        $victimSession = $this->login('session-owner@test.it', UserFactory::DEFAULT_PASSWORD);

        // Un altro utente, membro di entrambe, passa il refresh token della vittima nel body
        [$attacker, , $attackerToken] = $this->createAuthenticatedUser();
        $otherOrg = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne(['user' => $attacker, 'organization' => $otherOrg, 'role' => OrgRole::OWNER]);

        $this->jsonRequest('POST', '/api/auth/switch-org', [
            'organizationId' => $otherOrg->getId(),
            'refreshToken' => $victimSession['refresh_token'],
        ], accessToken: $attackerToken);
        self::assertResponseIsSuccessful();

        $row = $this->em()->getConnection()->fetchAssociative('SELECT active_organization_id FROM refresh_tokens WHERE user_id = ?', [$victim->getId()]);
        self::assertIsArray($row, 'La sessione della vittima esiste');
        self::assertNull($row['active_organization_id'], 'La sessione della vittima non deve cambiare organizzazione');
    }

    // -------------------- helpers --------------------

    /** @return array{access_token: string, refresh_token: string} */
    private function login(string $email, string $password): array
    {
        $this->jsonRequest('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
        $body = $this->jsonBody();

        return ['access_token' => (string) ($body['access_token'] ?? ''), 'refresh_token' => (string) ($body['refresh_token'] ?? '')];
    }

    private function issueRefreshToken(User $user): string
    {
        return static::getContainer()->get(RefreshTokenService::class)->issue($user)->getPlainToken();
    }

    private function activeOrgOf(string $jwt): ?int
    {
        $payload = json_decode(base64_decode(strtr(explode('.', $jwt)[1], '-_', '+/')), true, flags: JSON_THROW_ON_ERROR);

        return $payload['active_org_id'] ?? null;
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
