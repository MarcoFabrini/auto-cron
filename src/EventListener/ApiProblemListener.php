<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use App\Validator\ViolationKey;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Porta nel formato del progetto gli errori HTTP lanciati come eccezione sotto /api: ci pensa
 * lui, non l'header Accept (il frontend ne manda uno qualunque e Symfony risponderebbe in HTML).
 *
 * - `createNotFoundException('vehicle.not_found')` e simili: se il messaggio è una chiave i18n
 *   resta il `title`; altrimenti `http.<status>` (niente messaggi interni del router o dei voter).
 * - violazioni di #[MapRequestPayload] (422): `validation_failed` + `errors[{field, message}]`, come
 *   le risposte costruite a mano dai controller; il messaggio è una chiave i18n ({@see ViolationKey}).
 *
 * Gli errori 5xx e le eccezioni non HTTP restano a Symfony (log e pagina d'errore): il frontend
 * li riduce comunque a `http.<status>`.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ApiProblemListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        if (!$exception instanceof HttpExceptionInterface || $exception->getStatusCode() >= 500) {
            return;
        }

        $status = $exception->getStatusCode();
        $violations = $status === 422 ? $this->violationsOf($exception) : null;

        $problem = ['type' => 'about:blank', 'title' => $violations !== null ? 'validation_failed' : $this->titleOf($exception), 'status' => $status];
        if ($violations !== null) {
            $problem['errors'] = $violations;
        }

        $event->setResponse(new JsonResponse($problem, $status, $exception->getHeaders()));
    }

    private function titleOf(HttpExceptionInterface $exception): string
    {
        $message = $exception->getMessage();

        return preg_match(ViolationKey::PATTERN, $message) === 1 ? $message : 'http.'.$exception->getStatusCode();
    }

    /** @return list<array{field: string, message: string}>|null */
    private function violationsOf(\Throwable $exception): ?array
    {
        for ($e = $exception; $e !== null; $e = $e->getPrevious()) {
            if ($e instanceof ValidationFailedException) {
                return ViolationKey::errors($e->getViolations());
            }
        }

        return null;
    }
}
