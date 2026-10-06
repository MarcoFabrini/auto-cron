<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\OrganizationInvitation;
use App\Entity\OrganizationMember;
use App\Entity\User;
use App\Enum\OrgRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrganizationInvitation>
 */
class OrganizationInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganizationInvitation::class);
    }

    /**
     * L'invito esiste, non è scaduto né usato e chi l'ha emesso può ancora invitare: se `invitedBy`
     * non è più un membro accettato con ruolo owner/admin dell'org (rimosso, declassato) l'invito
     * è come non esistesse. `invitedBy` nullo (utente cancellato) mantiene il comportamento storico.
     */
    public function findValidByHash(string $tokenHash): ?OrganizationInvitation
    {
        $token = $this->findOneBy(['tokenHash' => $tokenHash]);
        if ($token === null || !$token->isValid()) {
            return null;
        }

        return $this->inviterCanStillInvite($token) ? $token : null;
    }

    private function inviterCanStillInvite(OrganizationInvitation $invitation): bool
    {
        $inviter = $invitation->getInvitedBy();
        if ($inviter === null) {
            return true;
        }

        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(OrganizationMember::class, 'm')
            ->where('m.organization = :org')
            ->andWhere('m.user = :inviter')
            ->andWhere('m.acceptedAt IS NOT NULL')
            ->andWhere('m.role IN (:roles)')
            ->setParameter('org', $invitation->getOrganization())
            ->setParameter('inviter', $inviter)
            ->setParameter('roles', [OrgRole::OWNER, OrgRole::ADMIN])
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * Inviti pendenti (non usati, non scaduti) di un'organizzazione.
     *
     * @return list<OrganizationInvitation>
     */
    public function findPendingByOrganization(Organization $org): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.organization = :org')
            ->andWhere('i.usedAt IS NULL')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('org', $org)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Marca usati gli inviti pendenti per (org, email): un solo invito attivo per coppia. */
    public function invalidateForOrgEmail(Organization $org, string $email): void
    {
        $this->createQueryBuilder('i')
            ->update()
            ->set('i.usedAt', ':now')
            ->where('i.organization = :org')
            ->andWhere('i.email = :email')
            ->andWhere('i.usedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('org', $org)
            ->setParameter('email', $email)
            ->getQuery()
            ->execute();
    }

    /**
     * Elimina gli inviti non ancora accettati spediti da $inviter (in una sola org se indicata):
     * un invito non deve sopravvivere a chi lo ha emesso. Restituisce quanti ne ha cancellati.
     */
    public function deletePendingByInviter(User $inviter, ?Organization $org = null): int
    {
        $qb = $this->createQueryBuilder('i')
            ->delete()
            ->where('i.invitedBy = :inviter')
            ->andWhere('i.usedAt IS NULL')
            ->setParameter('inviter', $inviter);
        if ($org !== null) {
            $qb->andWhere('i.organization = :org')->setParameter('org', $org);
        }

        return (int) $qb->getQuery()->execute();
    }

    public function deleteExpired(\DateTimeImmutable $olderThan): int
    {
        return (int) $this->createQueryBuilder('i')
            ->delete()
            ->where('i.expiresAt < :cutoff')
            ->setParameter('cutoff', $olderThan)
            ->getQuery()
            ->execute();
    }

    /**
     * Marca il token come usato SOLO se non lo è già (UPDATE condizionale): true per la sola richiesta
     * che lo consuma. Due richieste concorrenti con lo stesso token non passano entrambe.
     */
    public function consume(OrganizationInvitation $token): bool
    {
        return $this->createQueryBuilder('i')
            ->update()
            ->set('i.usedAt', ':now')
            ->where('i.id = :id')
            ->andWhere('i.usedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('id', $token->getId())
            ->getQuery()
            ->execute() === 1;
    }
}
