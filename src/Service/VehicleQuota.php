<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Repository\VehicleRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Tetto opzionale di veicoli per organizzazione (`VEHICLES_ORG_LIMIT`, 0 = nessun limite). Chi ospita
 * l'istanza può così impedire che un'organizzazione cresca senza controllo.
 *
 * Contano TUTTI i veicoli dell'organizzazione, archiviati inclusi: altrimenti archiviare e ricreare
 * aggirerebbe il tetto. Togliere veicoli (eliminarli) libera posti, archiviarli no.
 *
 * Il controllo avviene prima del salvataggio e non è atomico (nessun lock): due creazioni concorrenti
 * possono sforare il limite di uno. È un tetto di protezione, non un contatore contabile (come
 * {@see AttachmentQuota}).
 */
final class VehicleQuota
{
    public const LIMIT_REACHED = 'vehicle.limit_reached';

    public function __construct(
        private readonly VehicleRepository $repo,
        #[Autowire(env: 'int:default:app_vehicles_org_limit_default:VEHICLES_ORG_LIMIT')]
        private readonly int $orgLimit,
    ) {
    }

    /**
     * Chiave i18n dell'errore se l'organizzazione non può avere un altro veicolo, null se ci sta.
     * Il limite è inclusivo: con limite N l'N-esimo veicolo è ammesso, l'(N+1)-esimo no.
     */
    public function violation(Organization $org): ?string
    {
        if ($this->orgLimit > 0 && $this->repo->countByOrganization($org) >= $this->orgLimit) {
            return self::LIMIT_REACHED;
        }

        return null;
    }
}
