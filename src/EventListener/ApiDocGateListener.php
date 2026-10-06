<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tiene chiusa la documentazione OpenAPI (`/api/doc`, `/api/doc.json`) finché non la si accende con
 * `API_DOC_ENABLED=true`: da spenta risponde come una rotta inesistente (404 Problem Details tramite
 * {@see ApiProblemListener}), senza rivelare che esiste. Le due rotte restano in `app.api_public_pattern`
 * (accesso senza login quando è accesa).
 *
 * Priorità 16: dopo il RouterListener (32), che valorizza `_route`, e prima del firewall (8).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
final class ApiDocGateListener
{
    /** Nomi delle rotte in config/routes/nelmio_api_doc.yaml */
    private const array DOC_ROUTES = ['app.api_doc_ui', 'app.api_doc_json'];

    public function __construct(
        #[Autowire('%env(bool:API_DOC_ENABLED)%')]
        private readonly bool $enabled,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if ($this->enabled || !$event->isMainRequest()) {
            return;
        }

        if (\in_array($event->getRequest()->attributes->get('_route'), self::DOC_ROUTES, true)) {
            throw new NotFoundHttpException();
        }
    }
}
