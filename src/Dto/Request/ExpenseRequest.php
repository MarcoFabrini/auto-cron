<?php

declare(strict_types=1);

namespace App\Dto\Request;

use App\Enum\ExpenseCategory;
use App\Enum\RecurringPeriod;
use App\Validator\DecimalString;
use Symfony\Component\Validator\Constraints as Assert;

final class ExpenseRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $vehicleId = 0,

        #[Assert\NotBlank]
        #[Assert\Date]
        public string $occurredAt = '',

        public ExpenseCategory $category = ExpenseCategory::OTHER,

        #[Assert\NotBlank]
        #[Assert\Length(max: 500)]
        public string $description = '',

        #[DecimalString(maxDecimals: 2, message: 'validation.amount_format')]
        public string $amount = '0',

        public bool $recurring = false,
        public ?RecurringPeriod $recurringPeriod = null,

        public ?string $notes = null,
    ) {
    }
}
