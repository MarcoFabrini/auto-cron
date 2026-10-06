<?php

declare(strict_types=1);

namespace App\Dto\Request;

use App\Enum\ReminderType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Payload della PUT: come {@see ReminderRequest} senza `vehicleId`, perché un promemoria non
 * cambia veicolo. Un `vehicleId` presente nel body (il client storico lo manda) è ignorato dal
 * deserializzatore, che scarta le chiavi sconosciute.
 */
final class UpdateReminderRequest
{
    public function __construct(
        public ReminderType $type = ReminderType::CUSTOM,

        #[Assert\NotBlank]
        #[Assert\Length(max: 500)]
        public string $description = '',

        #[Assert\Date]
        public ?string $dueDate = null,

        #[Assert\Range(min: 0, max: 9_999_999, notInRangeMessage: 'common.km_too_large')]
        public ?int $dueKm = null,

        #[Assert\Range(min: 0, max: 365)]
        public int $notifyDaysBefore = 30,
    ) {
    }

    /** Senza scadenza (data o km) il promemoria non scatterebbe mai. */
    #[Assert\Callback]
    public function validateHasDue(ExecutionContextInterface $context): void
    {
        if (($this->dueDate === null || $this->dueDate === '') && $this->dueKm === null) {
            $context->buildViolation('reminder.due_required')->atPath('dueDate')->addViolation();
        }
    }
}
