<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sessioni a refresh token con rotazione e rilevamento del riuso.
 *
 * Ogni login apre una famiglia di token (`family_id`); ogni `/refresh` revoca il token presentato e ne
 * emette uno nuovo nella stessa famiglia. Un token già ruotato che ricompare dopo la finestra di tolleranza
 * ({@see self::REUSE_GRACE_SECONDS}) vuol dire che due parti ne hanno una copia: chi l'ha rubato o il
 * client legittimo. Non si può sapere chi, quindi si revoca TUTTA la famiglia e l'utente rifà il login.
 * Entro la finestra è solo una corsa tra richieste legittime (due schede, un retry dopo un errore di rete):
 * la perdente riceve 401 come sempre, senza effetti sulla famiglia.
 *
 * Un token scaduto non conta come riuso (ormai inutilizzabile), né uno revocato per altro motivo (logout,
 * cambio password: `revoked_at` senza `rotated_at`).
 */
final class RefreshTokenService
{
    public const TTL_DAYS = 7;
    public const COOKIE_NAME = 'refresh_token';

    /** Secondi dopo la rotazione in cui un secondo uso dello stesso token è una corsa e non un riuso. */
    public const REUSE_GRACE_SECONDS = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RefreshTokenRepository $repo,
        #[Autowire(service: 'monolog.logger.security')]
        private readonly LoggerInterface $logger,
    ) {
    }

    /** Senza `$familyId` apre una nuova famiglia (nuovo login o sessione ricreata); la rotazione passa la propria. */
    public function issue(User $user, ?int $activeOrganizationId = null, ?string $familyId = null): RefreshToken
    {
        $token = bin2hex(random_bytes(48)); // 96 chars, consegnato al client e mai salvato
        $expiresAt = (new \DateTimeImmutable())->modify('+'.self::TTL_DAYS.' days');

        // In DB solo l'hash: un dump o un backup non permettono di rubare le sessioni attive.
        $refreshToken = new RefreshToken($user, self::hash($token), $expiresAt, $familyId);
        $refreshToken->setPlainToken($token);
        $refreshToken->setActiveOrganizationId($activeOrganizationId);
        $this->em->persist($refreshToken);
        $this->em->flush();

        return $refreshToken;
    }

    /**
     * Ruota il token mantenendo l'org attiva e la famiglia. Null se un'altra richiesta l'ha già ruotato
     * (due refresh concorrenti con lo stesso token non ottengono due sessioni).
     */
    public function rotate(RefreshToken $oldToken): ?RefreshToken
    {
        if (!$this->repo->rotateIfActive($oldToken)) {
            return null;
        }

        return $this->issue($oldToken->getUser(), $oldToken->getActiveOrganizationId(), $oldToken->getFamilyId());
    }

    /**
     * Da chiamare quando `/refresh` rifiuta un token (`findValid()` null): distingue un token ruotato da poco
     * (corsa legittima), uno ruotato da tempo (riuso: revoca la famiglia e lo scrive nel log) e tutto il
     * resto. Il chiamante risponde 401 uguale in tutti e tre i casi: l'esito non va mai rivelato al client.
     */
    public function classifyRejected(string $plainToken): RefreshRejection
    {
        $token = $this->repo->findOneByHash(self::hash($plainToken));
        $rotatedAt = $token?->getRotatedAt();
        if ($token === null || $rotatedAt === null || $token->isExpired()) {
            return RefreshRejection::Invalid;
        }

        $now = new \DateTimeImmutable();
        if ($rotatedAt > $now->modify('-'.self::REUSE_GRACE_SECONDS.' seconds')) {
            return RefreshRejection::RaceLost;
        }

        $revoked = $this->repo->revokeFamily($token->getFamilyId());
        // Mai il token (nemmeno il suo hash): solo gli id che servono a chi amministra l'istanza.
        $this->logger->warning('Refresh token reuse detected: session family revoked', [
            'user_id' => $token->getUser()->getId(),
            'family_id' => $token->getFamilyId(),
            'revoked_tokens' => $revoked,
        ]);

        return RefreshRejection::Reuse;
    }

    public function setActiveOrganization(RefreshToken $token, int $organizationId): void
    {
        $token->setActiveOrganizationId($organizationId);
        $this->em->flush();
    }

    public function revoke(RefreshToken $token): void
    {
        $token->revoke();
        $this->em->flush();
    }

    public function revokeAllForUser(User $user): void
    {
        $this->repo->revokeAllForUser($user);
    }

    public function findValid(string $token): ?RefreshToken
    {
        return $this->repo->findValidByToken(self::hash($token));
    }

    /** Il token ha 384 bit di entropia: sha256 senza sale basta (non è una password). */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
