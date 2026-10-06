<?php

declare(strict_types=1);

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTFailureEventInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Token mancante, non valido o scaduto: Lexik risponderebbe con il suo `{"code":401,"message":"JWT Token not found"}`,
 * a differenza di ogni altro errore sotto /api (Problem Details, vedi {@see ApiProblemListener}). Qui lo si porta
 * alla stessa forma, con la chiave `http.401` che il frontend traduce già: il messaggio inglese di Lexik
 * non finisce in un toast e non rivela se il token era assente, malformato o scaduto.
 *
 * Il client non dipende dal corpo del 401: su qualunque 401 prova il refresh (frontend/src/api/client.ts).
 */
#[AsEventListener(event: Events::JWT_NOT_FOUND)]
#[AsEventListener(event: Events::JWT_INVALID)]
#[AsEventListener(event: Events::JWT_EXPIRED)]
final class JwtFailureListener
{
    public function __invoke(JWTFailureEventInterface $event): void
    {
        $event->setResponse(new JsonResponse(
            ['type' => 'about:blank', 'title' => 'http.401', 'status' => 401],
            401,
            ['WWW-Authenticate' => 'Bearer'],
        ));
    }
}
