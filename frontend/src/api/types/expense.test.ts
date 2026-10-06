import { describe, it, expect } from 'vitest';
import { adaptExpense, type ExpenseResponse } from './expense';

const raw = (over: Partial<ExpenseResponse>): ExpenseResponse => ({
  id: 1,
  vehicle: { id: 7, name: 'Golf', brand: 'VW', model: 'Golf' },
  occurredAt: '2026-01-15T00:00:00+00:00',
  category: 'insurance',
  description: 'Polizza',
  amount: '650.00',
  recurring: true,
  recurringPeriod: 'yearly',
  ...over,
});

describe('adaptExpense — recurringUntil', () => {
  it('riduce la data di fine a YYYY-MM-DD', () => {
    expect(adaptExpense(raw({ recurringUntil: '2027-01-15T00:00:00+00:00' })).recurringUntil).toBe('2027-01-15');
  });

  it('null o assente → null (spesa in corso)', () => {
    expect(adaptExpense(raw({ recurringUntil: null })).recurringUntil).toBeNull();
    expect(adaptExpense(raw({})).recurringUntil).toBeNull();
  });
});
