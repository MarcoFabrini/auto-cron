import { z } from 'zod';
import { normalizeDecimal } from '@/lib/decimal';

interface DecimalOptions {
  /** Cifre prima del punto che entrano nella colonna NUMERIC del backend (p - s). */
  maxIntegerDigits?: number;
  /** Zero ammesso (es. manutenzione gratuita). Di default l'importo deve essere > 0. */
  allowZero?: boolean;
}

/**
 * Decimale digitato ("45,50" o "45.50") con al più `maxDecimals` cifre decimali.
 * L'output è sempre col punto ("45.50"), il formato che si aspetta il backend.
 *
 * Gli stessi limiti del backend (`DecimalString`): stesse cifre, niente zero, niente valori più
 * grandi della colonna — così l'errore compare sul campo invece di un 422 al submit.
 */
export const decimalString = (maxDecimals: number, { maxIntegerDigits = 8, allowZero = false }: DecimalOptions = {}) =>
  z
    .string()
    .transform(normalizeDecimal)
    .pipe(
      z
        .string()
        .regex(new RegExp(`^\\d+(\\.\\d{1,${maxDecimals}})?$`), 'common.invalid_decimal')
        .refine((v) => (v.split('.')[0] ?? '').length <= maxIntegerDigits, 'common.decimal_too_large')
        .refine((v) => allowZero || Number(v) > 0, 'common.must_be_positive'),
    );
