import { describe, it, expect } from 'vitest';
import {
  formatKm,
  formatCurrency,
  formatDate,
  formatDecimal,
  formatBytes,
  todayIso,
  daysUntil,
} from './format';

describe('formatKm', () => {
  it('raggruppa le migliaia (it-IT) e aggiunge " km"', () => {
    expect(formatKm(12345)).toBe('12.345 km');
  });
  it('arrotonda i decimali via', () => {
    expect(formatKm(0)).toBe('0 km');
  });
});

describe('formatCurrency', () => {
  it('ritorna "—" per null/undefined/stringa vuota', () => {
    expect(formatCurrency(null)).toBe('—');
    expect(formatCurrency(undefined)).toBe('—');
    expect(formatCurrency('')).toBe('—');
  });
  it('ritorna "—" per valori non numerici', () => {
    expect(formatCurrency('abc')).toBe('—');
  });
  it('formatta i decimal string (virgola decimale it-IT + €)', () => {
    // it-IT non raggruppa i numeri a 4 cifre (CLDR minimumGroupingDigits).
    const out = formatCurrency('1234.5');
    expect(out).toMatch(/1234,50/);
    expect(out).toContain('€');
  });
  it('raggruppa le migliaia da 5 cifre in su', () => {
    expect(formatCurrency(12345.5)).toMatch(/12\.345,50/);
  });
  it('formatta i number', () => {
    expect(formatCurrency(0)).toContain('0,00');
  });
});

describe('formatDate', () => {
  it('formatta una data ISO (it-IT)', () => {
    const out = formatDate('2026-05-27');
    expect(out).toContain('2026');
    expect(out).toContain('mag');
    expect(out).toContain('27');
  });
  it('una data di calendario non slitta di giorno in un fuso a ovest di Greenwich', () => {
    const prev = process.env.TZ;
    process.env.TZ = 'America/Los_Angeles';
    try {
      expect(formatDate('2026-05-27')).toContain('27');
    } finally {
      if (prev === undefined) delete process.env.TZ;
      else process.env.TZ = prev;
    }
  });
  it('ritorna "—" per null/undefined', () => {
    expect(formatDate(null)).toBe('—');
    expect(formatDate(undefined)).toBe('—');
  });
  it('ritorna l\'input grezzo se non parsabile', () => {
    expect(formatDate('non-una-data')).toBe('non-una-data');
  });
});

describe('formatBytes', () => {
  it('byte sotto 1024 restano in B', () => {
    expect(formatBytes(512)).toBe('512 B');
  });
  it('scala a KB/MB', () => {
    expect(formatBytes(2048)).toBe('2 KB');
    expect(formatBytes(5 * 1024 * 1024)).toBe('5 MB');
  });
  it('usa la virgola decimale (it-IT)', () => {
    expect(formatBytes(1536)).toBe('1,5 KB');
  });
});

describe('formatDecimal', () => {
  it('usa la virgola (it-IT) e limita i decimali', () => {
    expect(formatDecimal(8.4567, 1)).toBe('8,5');
    expect(formatDecimal(12, 2)).toBe('12');
  });
  it('ritorna "—" per null/undefined/NaN', () => {
    expect(formatDecimal(null)).toBe('—');
    expect(formatDecimal(undefined)).toBe('—');
    expect(formatDecimal(Number.NaN)).toBe('—');
  });
});

describe('todayIso', () => {
  it('ritorna formato YYYY-MM-DD', () => {
    expect(todayIso()).toMatch(/^\d{4}-\d{2}-\d{2}$/);
  });
  it('è il giorno LOCALE: dopo la mezzanotte locale non resta a ieri (come farebbe toISOString in UTC)', () => {
    // 00:30 locale del 25/09: in un fuso a est di UTC (Italia) toISOString direbbe ancora il 24.
    expect(todayIso(new Date(2026, 8, 25, 0, 30))).toBe('2026-09-25');
    expect(todayIso(new Date(2026, 0, 5, 23, 59))).toBe('2026-01-05');
  });
});

describe('daysUntil', () => {
  const now = new Date(2026, 8, 25, 15, 0); // 25/09/2026 15:00 locale
  it('oggi = 0 (mai negativo, anche se la mezzanotte è già passata)', () => {
    expect(daysUntil('2026-09-25', now)).toBe(0);
    expect(daysUntil('2026-09-25', new Date(2026, 8, 25, 23, 59))).toBe(0);
  });
  it('domani = 1, ieri = -1', () => {
    expect(daysUntil('2026-09-26', now)).toBe(1);
    expect(daysUntil('2026-09-24', now)).toBe(-1);
  });
  it('conta giorni interi anche a cavallo del cambio ora legale (ott 2026)', () => {
    expect(daysUntil('2026-10-26', now)).toBe(31);
  });
  it('null se non è una data YYYY-MM-DD', () => {
    expect(daysUntil('25/09/2026', now)).toBeNull();
    expect(daysUntil('', now)).toBeNull();
  });
});
