<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Repository\OrganizationMemberRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Aggiunge al JWT i claim necessari per la business logic:
 * - `active_org_id`: l'organizzazione corrente (la prima accettata dell'utente)
 * - `user_id`: comodità per evitare un lookup quando serve l'id
 *
 * Lexik dispatcha l'evento usando la stringa `Events::JWT_CREATED`, non il FQN della classe,
 * quindi va registrato il listener con la stringa esplicita.
 */
#[AsEventListener(event: Events::JWT_CREATED)]
final class JwtCreatedListener
{
    public function __construct(
        private readonly OrganizationMemberRepository $memberRepo,
    ) {
    }

    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $payload['user_id'] = $user->getId();

        // Rispetta `active_org_id` se già passato esplicitamente (es. da test o switch-org).
        // Altrimenti default alla prima membership accepted dell'utente.
        if (!isset($payload['active_org_id'])) {
            $memberships = $this->memberRepo->findAllForUser($user);
            if (!empty($memberships)) {
                $payload['active_org_id'] = $memberships[0]->getOrganization()->getId();
            }
        }

        $event->setData($payload);
    }
}
