/**
 * Formatters condivisi cross-feature.
 * Per evitare duplicazione delle string concat e Intl init.
 */

const KM_FORMATTER = new Intl.NumberFormat('it-IT', { maximumFractionDigits: 0 });

/** "12.345 km" */
export function formatKm(km: number): string {
  return `${KM_FORMATTER.format(km)} km`;
}

/** "8,4" — numero con al più `maxFractionDigits` decimali (it-IT). Null → "—". */
export function formatDecimal(n: number | null | undefined, maxFractionDigits = 1): string {
  if (n === null || n === undefined || Number.isNaN(n)) return '—';
  return new Intl.NumberFormat('it-IT', { maximumFractionDigits: maxFractionDigits }).format(n);
}

/** "1.234,50 €" — accetta decimal string ("123.45") o number. Null → "—". */
export function formatCurrency(amount: string | number | null | undefined, locale = 'it-IT', currency = 'EUR'): string {
  if (amount === null || amount === undefined || amount === '') return '—';
  const n = typeof amount === 'string' ? Number.parseFloat(amount) : amount;
  if (Number.isNaN(n)) return '—';
  return new Intl.NumberFormat(locale, { style: 'currency', currency }).format(n);
}

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

/**
 * Date "2026-05-27" → "27 mag 2026" (locale-aware).
 * Una data di calendario ("YYYY-MM-DD") non ha fuso: va formattata in UTC, altrimenti
 * in un fuso a ovest di Greenwich mostra il giorno prima. Un datetime resta locale.
 */
export function formatDate(iso: string | null | undefined, locale = 'it-IT'): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return new Intl.DateTimeFormat(locale, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    ...(ISO_DATE.test(iso) && { timeZone: 'UTC' }),
  }).format(d);
}

/** Giorno di calendario LOCALE "YYYY-MM-DD" (mai `toISOString`: è UTC e slitta a cavallo della mezzanotte). */
export function toLocalIsoDate(d: Date): string {
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${mm}-${dd}`;
}

/** Data di oggi (locale) formato "YYYY-MM-DD" per default form. */
export function todayIso(now: Date = new Date()): string {
  return toLocalIsoDate(now);
}

/**
 * Giorni di calendario da oggi (locale) a `iso` ("YYYY-MM-DD"): 0 = oggi, negativo = passata.
 * Confronta giorni interi, non istanti: una scadenza di oggi non è mai "scaduta".
 * Null se `iso` non è una data valida.
 */
export function daysUntil(iso: string, now: Date = new Date()): number | null {
  if (!ISO_DATE.test(iso)) return null;
  const [y = 0, m = 1, d = 1] = iso.split('-').map(Number);
  const due = Date.UTC(y, m - 1, d);
  const today = Date.UTC(now.getFullYear(), now.getMonth(), now.getDate());
  return Math.round((due - today) / 86_400_000);
}

/** Byte → "1,2 MB" / "340 KB" (base 1024, locale-aware). */
export function formatBytes(bytes: number, locale = 'it-IT'): string {
  if (bytes < 1024) return `${bytes} B`;
  const units = ['KB', 'MB', 'GB'];
  let value = bytes / 1024;
  let unit = 0;
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024;
    unit += 1;
  }
  const formatted = new Intl.NumberFormat(locale, { maximumFractionDigits: 1 }).format(value);
  return `${formatted} ${units[unit]}`;
}
