<?php

declare(strict_types=1);

namespace App\Dto\Request;

use App\Enum\MaintenanceCategory;
use App\Enum\MaintenanceType;
use App\Validator\DecimalString;
use Symfony\Component\Validator\Constraints as Assert;

final class MaintenanceRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $vehicleId = 0,

        #[Assert\NotBlank]
        #[Assert\Date]
        public string $performedAt = '',

        #[Assert\PositiveOrZero]
        public int $km = 0,

        public MaintenanceType $type = MaintenanceType::OTHER,
        public MaintenanceCategory $category = MaintenanceCategory::SCHEDULED,

        #[Assert\NotBlank]
        public string $description = '',

        #[DecimalString(maxDecimals: 2, message: 'validation.amount_format')]
        public ?string $cost = null,

        #[Assert\Length(max: 200)]
        public ?string $workshop = null,
    ) {
    }
}
