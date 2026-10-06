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

    /**
     * Chi può ricevere la proprietà di un veicolo dell'org: membri accettati, di qualsiasi ruolo, non
     * anonimizzati, escluso l'attuale proprietario ($except, se c'è). Ordinati per cognome e nome.
     *
     * @return list<OrganizationMember>
     */
    public function findTransferCandidates(Organization $org, ?User $except): array
    {
        $qb = $this->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')
            ->addSelect('u')
            ->where('m.organization = :org')
            ->andWhere('m.acceptedAt IS NOT NULL')
            ->andWhere('u.email NOT LIKE :anonymized')
            ->setParameter('org', $org)
            ->setParameter('anonymized', '%'.User::ANONYMIZED_EMAIL_SUFFIX)
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->addOrderBy('u.id', 'ASC');
        if ($except !== null) {
            $qb->andWhere('m.user != :except')->setParameter('except', $except);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Il destinatario di un trasferimento, solo se è davvero un candidato: membro accettato e non
     * anonimizzato dell'org. Per ogni altro caso (id sconosciuto, altra org, invito pendente) torna null:
     * il chiamante risponde allo stesso modo, senza far capire quali account esistono.
     */
    public function findTransferRecipient(Organization $org, int $userId): ?OrganizationMember
    {
        /** @var OrganizationMember|null $member */
        $member = $this->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')
            ->addSelect('u')
            ->where('m.organization = :org')
            ->andWhere('u.id = :userId')
            ->andWhere('m.acceptedAt IS NOT NULL')
            ->andWhere('u.email NOT LIKE :anonymized')
            ->setParameter('org', $org)
            ->setParameter('userId', $userId)
            ->setParameter('anonymized', '%'.User::ANONYMIZED_EMAIL_SUFFIX)
            ->getQuery()
            ->getOneOrNullResult();

        return $member;
    }

    /**
     * Quanti owner accettati (account anonimizzati esclusi) ha l'org, senza contare $except:
     * serve a sapere se togliere il ruolo a un owner lascerebbe l'org senza nessuno.
     */
    public function countAcceptedOwners(Organization $org, ?User $except = null): int
    {
        $qb = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->innerJoin('m.user', 'u')
            ->where('m.organization = :org')
            ->andWhere('m.role = :owner')
            ->andWhere('m.acceptedAt IS NOT NULL')
            ->andWhere('u.email NOT LIKE :anonymized')
            ->setParameter('org', $org)
            ->setParameter('owner', OrgRole::OWNER)
            ->setParameter('anonymized', '%'.User::ANONYMIZED_EMAIL_SUFFIX);
        if ($except !== null) {
            $qb->andWhere('m.user != :except')->setParameter('except', $except);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Il più anziano degli owner accettati dell'org, escluso $except e gli account anonimizzati:
     * a lui passano i veicoli di chi esce senza che nessuno glieli assegni esplicitamente.
     */
    public function findOldestAcceptedOwner(Organization $org, User $except): ?User
    {
        /** @var OrganizationMember|null $member */
        $member = $this->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')
            ->addSelect('u')
            ->where('m.organization = :org')
            ->andWhere('m.role = :owner')
            ->andWhere('m.acceptedAt IS NOT NULL')
            ->andWhere('m.user != :except')
            ->andWhere('u.email NOT LIKE :anonymized')
            ->setParameter('org', $org)
            ->setParameter('owner', OrgRole::OWNER)
            ->setParameter('except', $except)
            ->setParameter('anonymized', '%'.User::ANONYMIZED_EMAIL_SUFFIX)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $member?->getUser();
    }
}
