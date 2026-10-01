import { describe, expect, it } from 'vitest';
import { z } from 'zod';
import './errorMap';

const firstMessage = (result: z.SafeParseReturnType<unknown, unknown>) =>
  result.success ? null : result.error.issues[0]?.message;

describe('errorMap (messaggi zod di default → chiavi i18n)', () => {
  it('numero non intero', () => {
    expect(firstMessage(z.number().int().safeParse(1.5))).toBe('common.must_be_integer');
  });

  it('campo mancante', () => {
    expect(firstMessage(z.object({ km: z.number() }).safeParse({}))).toBe('common.required');
  });

  it('NaN (campo numerico lasciato vuoto)', () => {
    expect(firstMessage(z.number().safeParse(Number.NaN))).toBe('common.invalid_number');
  });

  it('lunghezze e intervalli', () => {
    expect(firstMessage(z.string().min(3).safeParse('a'))).toBe('common.too_short');
    expect(firstMessage(z.string().max(2).safeParse('abc'))).toBe('common.too_long');
    expect(firstMessage(z.number().min(5).safeParse(1))).toBe('common.too_small');
    expect(firstMessage(z.number().max(5).safeParse(9))).toBe('common.too_big');
  });

  it('un messaggio esplicito dello schema ha la precedenza', () => {
    expect(firstMessage(z.number().int('mio.messaggio').safeParse(1.5))).toBe('mio.messaggio');
  });
});
