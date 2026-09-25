<?php

declare(strict_types=1);

namespace App\Dto\Request;

use App\Enum\FuelType;
use App\Enum\VehicleType;
use Symfony\Component\Validator\Constraints as Assert;

final class VehicleRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name = '',

        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $brand = '',

        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $model = '',

        #[Assert\Range(min: 1900, max: 2100)]
        public int $year = 2024,

        public VehicleType $type = VehicleType::CAR,
        public FuelType $fuelType = FuelType::GASOLINE,

        /** Seconda fonte per veicoli bi-fuel (es. benzina+GPL, ibrido benzina+elettrico). Null se mono. */
        public ?FuelType $secondaryFuelType = null,

        #[Assert\Length(max: 20)]
        public ?string $licensePlate = null,

        #[Assert\Length(max: 17)]
        public ?string $vin = null,

        #[Assert\PositiveOrZero]
        public int $initialKm = 0,

        #[Assert\Length(max: 5000)]
        public ?string $notes = null,
    ) {
    }
}
