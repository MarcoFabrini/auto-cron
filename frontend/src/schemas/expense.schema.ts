import { z } from 'zod';
import { EXPENSE_CATEGORIES, RECURRING_PERIODS } from '@/api/types/expense';
import { decimalString } from './decimal';

export const expenseSchema = z
  .object({
    vehicleId: z.number({ message: 'expense.vehicle.required' }).int().positive(),
    occurredAt: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'common.invalid_date'),
    category: z.enum(EXPENSE_CATEGORIES, { message: 'expense.category.required' }),
    description: z.string().min(1, 'expense.description.required').max(2000),
    amount: decimalString(2),
    recurring: z.boolean(),
    recurringPeriod: z.enum(RECURRING_PERIODS).nullable().optional(),
    notes: z.string().max(2000).nullable().optional(),
  })
  .refine((d) => !d.recurring || !!d.recurringPeriod, {
    message: 'expense.recurring_period.required',
    path: ['recurringPeriod'],
  });

export type ExpenseFormData = z.infer<typeof expenseSchema>;
