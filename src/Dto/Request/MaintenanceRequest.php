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

        #[Assert\Range(min: 0, max: 9_999_999)]
        public int $km = 0,

        public MaintenanceType $type = MaintenanceType::OTHER,
        public MaintenanceCategory $category = MaintenanceCategory::SCHEDULED,

        #[Assert\NotBlank]
        #[Assert\Length(max: 5000)]
        public string $description = '',

        #[DecimalString(maxDecimals: 2, maxIntegerDigits: 8, allowZero: true, message: 'validation.amount_format')]
        public ?string $cost = null,

        #[Assert\Length(max: 200)]
        public ?string $workshop = null,
    ) {
    }
}
