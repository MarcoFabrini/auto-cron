<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => strtolower(trim($email))]);
    }

    /** L'istanza ha almeno un utente? Finché no, la registrazione libera è aperta (bootstrap del primo admin). */
    public function hasUsers(): bool
    {
        return $this->findOneBy([]) !== null;
    }

    /**
     * Il primo utente mai registrato è l'amministratore implicito dell'istanza
     * self-host (impostazioni globali VAPID — SMTP invece è solo env, vedi
     * AppMailer) — nessun flag/migrazione: id crescente = ordine di creazione,
     * stesso idioma di PushSettingsRepository per le config singleton di
     * istanza. Se quell'utente si elimina (GDPR), il ruolo passa
     * automaticamente al successivo più vecchio invece di lasciare l'istanza
     * senza admin.
     */
    public function isInstanceAdmin(User $user): bool
    {
        $first = $this->findOneBy([], ['id' => 'ASC']);
        return $first !== null && $first->getId() === $user->getId();
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}
