<?php

declare(strict_types=1);

namespace App\Service\Gdpr;

use App\Entity\User;
use App\Enum\OrgRole;
use App\Repository\PushSubscriptionRepository;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * GDPR Art. 17 — right to erasure (#5.5).
 *
 * Strategia: anonymize (non hard-delete) per preservare integrità referenziale
 * dei dati org-owned (vehicles, maintenance, ecc.) che potrebbero appartenere
 * ad altri membri dell'organization.
 *
 * Operazioni:
 * 1. Verifica precondizioni: l'utente NON deve essere unico owner di un'org
 *    con altri membri attivi (deve trasferire ownership prima).
 * 2. Anonymize PII (email, firstName, lastName, password) → email univoca
 *    pseudo-randomica `deleted-<id>-<hash>@anonymized.local`.
 * 3. Revoke tutti i refresh token attivi.
 * 4. Delete tutte le push subscription (token mobile, endpoint web).
 * 5. Per ogni org dove l'utente è unico membro → delete org (cascade).
 *    Negli altri casi le memberships restano (storia partecipazione).
 *
 * Il record `users` resta in DB con dati anonimi per:
 * - mantenere FK attachments.uploaded_by, vehicle_shares.invited_by
 * - audit log futuro (#3.6)
 * - rispettare cascadi DB esistenti
 */
final class GdprDeleteService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RefreshTokenRepository $tokenRepo,
        private readonly PushSubscriptionRepository $pushRepo,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    /**
     * @return array{anonymized_user_id: int, revoked_tokens: int, deleted_push_subs: int, deleted_orgs: int, kept_memberships: int}
     *
     * @throws GdprDeleteBlockedException se l'utente è ultimo owner di un'org condivisa
     */
    public function anonymize(User $user): array
    {
        $userId = $user->getId();
        if ($userId === null) {
            throw new \LogicException('Cannot anonymize unpersisted user');
        }

        $this->assertSafeToDelete($user);

        $deletedOrgs = 0;
        $keptMemberships = 0;

        // Drop org dove user è unico membro (solo proprietario, no altri member)
        foreach ($user->getMemberships()->toArray() as $m) {
            $org = $m->getOrganization();
            $allMembers = $org->getMembers()->toArray();
            $otherMembers = array_filter($allMembers, fn ($x) => $x->getUser()->getId() !== $userId);

            if ($otherMembers === []) {
                $this->em->remove($org);
                ++$deletedOrgs;
            } else {
                ++$keptMemberships;
            }
        }

        // Revoke refresh tokens attivi (count prima del revoke per audit)
        $activeTokens = $this->tokenRepo->countActiveForUser($user);
        $this->tokenRepo->revokeAllForUser($user);

        // Hard-delete push subscriptions (token device + endpoint web)
        $pushSubs = $this->pushRepo->findBy(['user' => $user]);
        foreach ($pushSubs as $sub) {
            $this->em->remove($sub);
        }
        $deletedPushSubs = count($pushSubs);

        // Anonymize PII
        $anonEmail = sprintf('deleted-%d-%s@anonymized.local', $userId, bin2hex(random_bytes(8)));
        $randomPassword = bin2hex(random_bytes(32));
        $user
            ->setEmail($anonEmail)
            ->setFirstName('Deleted')
            ->setLastName('User')
            ->setLocale('it')
            ->setPassword($this->hasher->hashPassword($user, $randomPassword));

        $this->em->flush();

        return [
            'anonymized_user_id' => $userId,
            'revoked_tokens' => $activeTokens,
            'deleted_push_subs' => $deletedPushSubs,
            'deleted_orgs' => $deletedOrgs,
            'kept_memberships' => $keptMemberships,
        ];
    }

    private function assertSafeToDelete(User $user): void
    {
        foreach ($user->getMemberships() as $m) {
            if ($m->getRole() !== OrgRole::OWNER) {
                continue;
            }
            $org = $m->getOrganization();
            $otherOwners = 0;
            $hasOtherMembers = false;
            foreach ($org->getMembers() as $other) {
                if ($other->getUser()->getId() === $user->getId()) {
                    continue;
                }
                $hasOtherMembers = true;
                if ($other->getRole() === OrgRole::OWNER) {
                    ++$otherOwners;
                }
            }

            if ($hasOtherMembers && $otherOwners === 0) {
                throw new GdprDeleteBlockedException(sprintf(
                    'User is sole owner of organization "%s" with %d other members. Transfer ownership first.',
                    $org->getSlug(),
                    $org->getMembers()->count() - 1,
                ));
            }
        }
    }
}
