<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\OrganizationMember;
use App\Entity\User;
use App\Enum\OrgRole;
use App\Repository\OrganizationInvitationRepository;
use App\Repository\OrganizationMemberRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;

/**
 * Cambio del ruolo d'organizzazione di un membro (`PATCH .../members/{memberId}`).
 *
 * Gerarchia stretta, decisa dal proprietario del prodotto:
 * - OWNER: potere assoluto, promuove e declassa chiunque (altri owner e admin inclusi) verso
 *   qualsiasi ruolo; è così che si passa il testimone (promuovi B a owner, poi A si declassa);
 * - ADMIN: può solo PROMUOVERE un `member` ad `admin`. Non declassa nessuno, non tocca altri admin
 *   né gli owner, non assegna `owner`, non cambia il proprio ruolo;
 * - MEMBER: niente (lo ferma già il voter MANAGE_MEMBERS, qui si ricontrolla con stato fresco).
 *
 * Invariante: l'org ha sempre almeno un owner accettato e non anonimizzato. Attraversa più righe, quindi
 * il controllo sta qui e non nell'entity, ed è fatto sotto un lock in scrittura sulla riga dell'org: due
 * owner che si declassano a vicenda nello stesso istante si serializzano e il secondo trova già il
 * primo declassato. Ruolo d'org e proprietà dei veicoli (share) sono indipendenti: qui non si tocca nessuna share.
 */
final class OrganizationMemberRoleChanger
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrganizationMemberRepository $memberRepo,
        private readonly OrganizationInvitationRepository $invitationRepo,
    ) {
    }

    /**
     * Matrice dei permessi, pura. Restituisce la chiave i18n del rifiuto (403) o null se il cambio è consentito.
     * Gli ADMIN controllano prima di tutto l'auto-modifica, poi il bersaglio (owner, admin), poi il ruolo
     * assegnato: così "admin che tocca un admin" resta `cannot_change_admin_role` anche se chiede `owner`.
     * L'ultimo owner e il no-op non sono permessi: li valuta {@see change()} dopo.
     */
    public static function denialKey(OrgRole $actorRole, OrgRole $targetRole, OrgRole $newRole, bool $isSelf): ?string
    {
        return match ($actorRole) {
            OrgRole::OWNER => null,
            OrgRole::MEMBER => MemberRoleChangeException::FORBIDDEN,
            OrgRole::ADMIN => match (true) {
                $isSelf => MemberRoleChangeException::CANNOT_CHANGE_OWN_ROLE,
                $targetRole === OrgRole::OWNER => MemberRoleChangeException::CANNOT_CHANGE_OWNER_ROLE,
                $targetRole === OrgRole::ADMIN => MemberRoleChangeException::CANNOT_CHANGE_ADMIN_ROLE,
                $newRole === OrgRole::OWNER => MemberRoleChangeException::OWNER_ROLE_FORBIDDEN,
                default => null,
            },
        };
    }

    /**
     * @return bool true se il ruolo è cambiato davvero (false = stesso ruolo: nessuna scrittura, audit o notifica)
     *
     * @throws MemberRoleChangeException
     */
    public function change(OrganizationMember $target, OrgRole $newRole, User $actor): bool
    {
        $org = $target->getOrganization();

        return $this->em->wrapInTransaction(function () use ($target, $newRole, $actor, $org): bool {
            $this->em->lock($org, LockMode::PESSIMISTIC_WRITE);

            // Dopo il lock si rilegge lo stato: tra il voter e qui un altro owner può aver cambiato i ruoli.
            $actorMembership = $this->memberRepo->findMembership($actor, $org);
            try {
                $this->em->refresh($target);
                if ($actorMembership !== null) {
                    $this->em->refresh($actorMembership);
                }
            } catch (EntityNotFoundException) {
                throw new MemberRoleChangeException('member.not_found', 404);
            }
            if ($actorMembership === null || !$actorMembership->isAccepted()) {
                throw MemberRoleChangeException::forbidden(MemberRoleChangeException::FORBIDDEN);
            }

            $denial = self::denialKey(
                $actorMembership->getRole(),
                $target->getRole(),
                $newRole,
                $actor->getId() === $target->getUser()->getId(),
            );
            if ($denial !== null) {
                throw MemberRoleChangeException::forbidden($denial);
            }

            $previous = $target->getRole();
            if ($previous === $newRole) {
                return false;
            }

            if ($previous === OrgRole::OWNER && $this->memberRepo->countAcceptedOwners($org, $target->getUser()) === 0) {
                throw MemberRoleChangeException::lastOwner();
            }

            $target->setRole($newRole);

            // Un invitante declassato non deve lasciare inviti spendibili (es. un invito `owner`):
            // come alla rimozione del membro, cadono tutti quelli pendenti nell'org.
            if (self::rank($newRole) < self::rank($previous)) {
                $this->invitationRepo->deletePendingByInviter($target->getUser(), $org);
            }

            return true;
        });
    }

    private static function rank(OrgRole $role): int
    {
        return match ($role) {
            OrgRole::OWNER => 3,
            OrgRole::ADMIN => 2,
            OrgRole::MEMBER => 1,
        };
    }
}
