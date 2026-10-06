import { z } from 'zod';
import { EXPENSE_CATEGORIES, RECURRING_PERIODS } from '@/api/types/expense';
import { decimalString } from './decimal';

export const expenseSchema = z
  .object({
    vehicleId: z.number({ message: 'expense.vehicle.required' }).int().positive(),
    occurredAt: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'common.invalid_date'),
    category: z.enum(EXPENSE_CATEGORIES, { message: 'expense.category.required' }),
    description: z.string().min(1, 'expense.description.required').max(500),
    amount: decimalString(2, { maxIntegerDigits: 8 }),
    recurring: z.boolean(),
    recurringPeriod: z.enum(RECURRING_PERIODS).nullable().optional(),
    // Ultima data di addebito (inclusa); vuota = la spesa continua finché non viene disdetta.
    recurringUntil: z
      .string()
      .regex(/^\d{4}-\d{2}-\d{2}$/, 'common.invalid_date')
      .nullable()
      .optional(),
    notes: z.string().max(2000).nullable().optional(),
  })
  .refine((d) => !d.recurring || !!d.recurringPeriod, {
    message: 'expense.recurring_period.required',
    path: ['recurringPeriod'],
  })
  // Le date ISO si confrontano come stringhe; la fine non può precedere il primo addebito.
  .refine((d) => !d.recurring || !d.recurringUntil || d.recurringUntil >= d.occurredAt, {
    message: 'expense.recurring_until.before_start',
    path: ['recurringUntil'],
  })
  // Periodo e data di fine valgono solo per le spese ricorrenti: se l'utente li imposta e poi disattiva
  // "ricorrente", non vanno inviati (il backend li rifiuta come incoerenti).
  .transform((d) => (d.recurring ? d : { ...d, recurringPeriod: null, recurringUntil: null }));

export type ExpenseFormData = z.infer<typeof expenseSchema>;
