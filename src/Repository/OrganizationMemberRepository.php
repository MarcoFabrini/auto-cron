<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleShare;
use App\Enum\OrgRole;
use App\Enum\ShareRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrganizationMember>
 */
class OrganizationMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganizationMember::class);
    }

    public function findMembership(User $user, Organization $organization): ?OrganizationMember
    {
        return $this->findOneBy([
            'user' => $user,
            'organization' => $organization,
        ]);
    }

    /**
     * @return list<OrganizationMember>
     */
    public function findAllForUser(User $user): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.organization', 'o')
            ->addSelect('o')
            ->where('m.user = :user')
            ->andWhere('m.acceptedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('o.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Chi riceve le notifiche di un veicolo: il suo proprietario (share `admin` accettato) con
     * membership accettata, esclusi gli account anonimizzati. Non owner/admin dell'org per i
     * veicoli altrui, né chi ha il veicolo in condivisione (sola lettura): a ciascuno arrivano
     * solo le scadenze dei propri veicoli.
     *
     * @return list<OrganizationMember>
     */
    public function findNotificationRecipients(Organization $org, Vehicle $vehicle): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')
            ->addSelect('u')
            ->innerJoin(VehicleShare::class, 'vs', 'WITH', 'vs.user = m.user AND vs.vehicle = :vehicle AND vs.role = :ownerRole AND vs.acceptedAt IS NOT NULL')
            ->where('m.organization = :org')
            ->andWhere('m.acceptedAt IS NOT NULL')
            ->andWhere('u.email NOT LIKE :anonymized')
            ->setParameter('org', $org)
            ->setParameter('vehicle', $vehicle)
            ->setParameter('ownerRole', ShareRole::ADMIN)
            ->setParameter('anonymized', '%'.User::ANONYMIZED_EMAIL_SUFFIX)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<OrganizationMember>
     */
    public function findAcceptedByOrganization(Organization $org): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')
            ->addSelect('u')
            ->where('m.organization = :org')
            ->andWhere('m.acceptedAt IS NOT NULL')
            ->setParameter('org', $org)
            ->getQuery()
            ->getResult();
    }

    /**
     * Membri accettati con cui ha senso condividere un veicolo: solo i `member`
     * (owner/admin vedono già tutto via ruolo org, una condivisione sarebbe
     * inerte), escluso chi condivide. Ordinati per cognome e nome.
     *
     * @return list<OrganizationMember>
     */
    public function findShareableMembers(Organization $org, User $except): array
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')
            ->addSelect('u')
            ->where('m.organization = :org')
            ->andWhere('m.acceptedAt IS NOT NULL')
            ->andWhere('m.role = :role')
            ->andWhere('m.user != :except')
            ->andWhere('u.email NOT LIKE :anonymized')
            ->setParameter('anonymized', '%'.User::ANONYMIZED_EMAIL_SUFFIX)
            ->setParameter('org', $org)
            ->setParameter('role', OrgRole::MEMBER)
            ->setParameter('except', $except)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
