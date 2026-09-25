import { z } from 'zod';
import { normalizeDecimal } from '@/lib/decimal';

/**
 * Decimale digitato ("45,50" o "45.50") con al più `maxDecimals` cifre decimali.
 * L'output è sempre col punto ("45.50"), il formato che si aspetta il backend.
 */
export const decimalString = (maxDecimals: number) =>
  z
    .string()
    .transform(normalizeDecimal)
    .pipe(z.string().regex(new RegExp(`^\\d+(\\.\\d{1,${maxDecimals}})?$`), 'common.invalid_decimal'));
