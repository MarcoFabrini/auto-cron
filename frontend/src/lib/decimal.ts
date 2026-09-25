/**
 * Numeri decimali digitati dall'utente. La tastiera italiana (iOS/Android, `inputMode="decimal"`)
 * offre la VIRGOLA; il backend vuole il punto ("45.50"). Si normalizza qui, prima di validare e
 * prima di inviare: l'utente non deve mai sapere che formato vuole l'API.
 */

/** "45,50" → "45.50" (trim incluso). Non tocca altro: la validazione resta allo schema. */
export function normalizeDecimal(value: string): string {
  return value.trim().replace(',', '.');
}

/** Numero da un input decimale (virgola o punto); NaN se non è un numero. */
export function parseDecimal(value: string): number {
  const v = normalizeDecimal(value);
  return v === '' ? Number.NaN : Number(v);
}
