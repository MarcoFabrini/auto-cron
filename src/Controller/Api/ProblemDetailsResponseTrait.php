<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Validator\ViolationKey;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\ConstraintViolationListInterface;

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

    /** 422 `validation_failed` con un errore per violazione ({field, message}, il messaggio è una chiave i18n). */
    private function validationProblem(ConstraintViolationListInterface $violations): JsonResponse
    {
        return $this->validationBody(ViolationKey::errors($violations));
    }

    /** 422 `validation_failed` per una regola che il controller verifica da sé su un solo campo. */
    private function fieldProblem(string $field, string $messageKey): JsonResponse
    {
        return $this->validationBody([['field' => $field, 'message' => $messageKey]]);
    }

    /** @param list<array{field: string, message: string}> $errors */
    private function validationBody(array $errors): JsonResponse
    {
        return new JsonResponse(
            ['type' => 'about:blank', 'title' => 'validation_failed', 'status' => 422, 'errors' => $errors],
            422,
        );
    }
}
