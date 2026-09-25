<?php

declare(strict_types=1);

namespace App\Dto\Request;

use App\Enum\ReminderType;
use Symfony\Component\Validator\Constraints as Assert;

final class ReminderRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $vehicleId = 0,

        public ReminderType $type = ReminderType::CUSTOM,

        #[Assert\NotBlank]
        #[Assert\Length(max: 500)]
        public string $description = '',

        #[Assert\Date]
        public ?string $dueDate = null,

        #[Assert\PositiveOrZero]
        public ?int $dueKm = null,

        #[Assert\PositiveOrZero]
        public int $notifyDaysBefore = 30,
    ) {
    }
}
