<?php

declare(strict_types=1);

namespace App\Dto\Request;

use App\Enum\ExpenseCategory;
use App\Enum\RecurringPeriod;
use App\Validator\DecimalString;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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

        #[DecimalString(maxDecimals: 2, maxIntegerDigits: 8, message: 'validation.amount_format')]
        public string $amount = '0',

        public bool $recurring = false,
        public ?RecurringPeriod $recurringPeriod = null,

        #[Assert\Length(max: 2000)]
        public ?string $notes = null,
    ) {
    }

    /** Il periodo ha senso solo per le spese ricorrenti (e lì è obbligatorio). */
    #[Assert\Callback]
    public function validateRecurrence(ExecutionContextInterface $context): void
    {
        if ($this->recurring && $this->recurringPeriod === null) {
            $context->buildViolation('expense.recurring_period_required')->atPath('recurringPeriod')->addViolation();
        }
        if (!$this->recurring && $this->recurringPeriod !== null) {
            $context->buildViolation('expense.recurring_period_unexpected')->atPath('recurringPeriod')->addViolation();
        }
    }
}
