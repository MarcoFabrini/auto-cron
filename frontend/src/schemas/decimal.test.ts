import { describe, it, expect } from 'vitest';
import { refuelingSchema } from './refueling.schema';
import { expenseSchema } from './expense.schema';
import { maintenanceSchema } from './maintenance.schema';
import { normalizeDecimal, parseDecimal } from '@/lib/decimal';

const refueling = { vehicleId: 1, refueledAt: '2026-09-25', km: 100, fuelType: 'diesel', fullTank: true };

describe('normalizeDecimal / parseDecimal', () => {
  it('virgola → punto, trim', () => {
    expect(normalizeDecimal(' 45,50 ')).toBe('45.50');
    expect(parseDecimal('1,859')).toBe(1.859);
    expect(parseDecimal('12.5')).toBe(12.5);
  });
  it('vuoto o non numerico → NaN', () => {
    expect(parseDecimal('')).toBeNaN();
    expect(parseDecimal('abc')).toBeNaN();
  });
});

describe('schemi con importi decimali: la virgola della tastiera italiana è accettata', () => {
  it('rifornimento: "42,5" e "1,859" passano e escono col punto (formato backend)', () => {
    const r = refuelingSchema.safeParse({ ...refueling, liters: '42,5', pricePerLiter: '1,859' });
    expect(r.success).toBe(true);
    if (r.success) {
      expect(r.data.liters).toBe('42.5');
      expect(r.data.pricePerLiter).toBe('1.859');
    }
  });
  it('rifornimento: il punto continua a funzionare', () => {
    const r = refuelingSchema.safeParse({ ...refueling, liters: '42.5', pricePerLiter: '1.859' });
    expect(r.success && r.data.liters).toBe('42.5');
  });
  it('rifornimento: rifiuta testo, separatori doppi e troppe cifre decimali', () => {
    for (const bad of ['abc', '4,5,0', '1,2345', '', ',5', '-3']) {
      expect(refuelingSchema.safeParse({ ...refueling, liters: bad, pricePerLiter: '1,8' }).success).toBe(false);
    }
  });
  it('spesa: importo con virgola (max 2 decimali)', () => {
    const base = { vehicleId: 1, occurredAt: '2026-09-25', category: 'other', description: 'x', recurring: false };
    const ok = expenseSchema.safeParse({ ...base, amount: '120,90' });
    expect(ok.success && ok.data.amount).toBe('120.90');
    expect(expenseSchema.safeParse({ ...base, amount: '120,999' }).success).toBe(false);
  });
  it('manutenzione: costo opzionale, con virgola quando presente', () => {
    const base = {
      vehicleId: 1,
      performedAt: '2026-09-25',
      km: 10,
      type: 'oil_change',
      category: 'scheduled',
      description: 'Tagliando',
    };
    const ok = maintenanceSchema.safeParse({ ...base, cost: '89,5' });
    expect(ok.success && ok.data.cost).toBe('89.5');
    expect(maintenanceSchema.safeParse({ ...base, cost: null }).success).toBe(true);
    expect(maintenanceSchema.safeParse(base).success).toBe(true);
  });
});
