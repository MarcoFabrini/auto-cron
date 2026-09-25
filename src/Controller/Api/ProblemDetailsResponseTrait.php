<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Risposta di errore RFC 7807 (application/problem+json semplificato) condivisa
 * dai controller API, per non duplicare il body {type, title, status}.
 */
trait ProblemDetailsResponseTrait
{
    /** Errore minimale {type, title, status} con HTTP status coerente. */
    private function problem(string $title, int $status): JsonResponse
    {
        return new JsonResponse(
            ['type' => 'about:blank', 'title' => $title, 'status' => $status],
            $status,
        );
    }
}
