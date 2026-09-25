export const EXPENSE_CATEGORIES = [
  'insurance',
  'road_tax',
  'tax',
  'parking',
  'toll',
  'fine',
  'accessory',
  'financing',
  'subscription',
  'garage',
  'car_wash',
  'theft_damage',
  'other',
] as const;
export type ExpenseCategory = (typeof EXPENSE_CATEGORIES)[number];

export const RECURRING_PERIODS = [
  'weekly',
  'monthly',
  'quarterly',
  'semiannual',
  'yearly',
  'biennial',
] as const;
export type RecurringPeriod = (typeof RECURRING_PERIODS)[number];

export interface ExpenseResponse {
  id: number;
  vehicle: { id: number; name: string; brand: string; model: string };
  occurredAt: string;          // ISO datetime
  category: ExpenseCategory;
  description: string;
  amount: string;              // decimal string
  recurring: boolean;
  recurringPeriod: RecurringPeriod | null;
  notes?: string | null;
}

export interface Expense {
  id: number;
  vehicleId: number;
  occurredAt: string;          // YYYY-MM-DD
  category: ExpenseCategory;
  description: string;
  amount: string;
  recurring: boolean;
  recurringPeriod: RecurringPeriod | null;
  notes: string | null;
}

export interface CreateExpenseDto {
  vehicleId: number;
  occurredAt: string;
  category: ExpenseCategory;
  description: string;
  amount: string;
  recurring: boolean;
  recurringPeriod?: RecurringPeriod | null;
  notes?: string | null;
}

export type UpdateExpenseDto = CreateExpenseDto;

export function adaptExpense(raw: ExpenseResponse): Expense {
  return {
    id: raw.id,
    vehicleId: raw.vehicle.id,
    occurredAt: raw.occurredAt.slice(0, 10),
    category: raw.category,
    description: raw.description,
    amount: raw.amount,
    recurring: raw.recurring,
    recurringPeriod: raw.recurringPeriod,
    notes: raw.notes ?? null,
  };
}
