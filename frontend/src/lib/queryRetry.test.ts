import { describe, it, expect } from 'vitest';
import { ApiError } from '@/api/client';
import { shouldRetryQuery } from './queryRetry';

describe('shouldRetryQuery', () => {
  it.each([400, 401, 403, 404, 409, 422, 429, 499])('un %i non viene mai ritentato', (status) => {
    const error = new ApiError('x', status);
    expect(shouldRetryQuery(0, error)).toBe(false);
    expect(shouldRetryQuery(1, error)).toBe(false);
  });

  it.each([500, 502, 503])('un %i viene ritentato fino a 2 volte', (status) => {
    const error = new ApiError('x', status);
    expect(shouldRetryQuery(0, error)).toBe(true);
    expect(shouldRetryQuery(1, error)).toBe(true);
    expect(shouldRetryQuery(2, error)).toBe(false);
  });

  it('un errore senza status (rete, ZodError) viene ritentato fino a 2 volte', () => {
    const error = new TypeError('Failed to fetch');
    expect(shouldRetryQuery(0, error)).toBe(true);
    expect(shouldRetryQuery(1, error)).toBe(true);
    expect(shouldRetryQuery(2, error)).toBe(false);
  });

  it('un valore lanciato che non è un oggetto non manda in errore la policy', () => {
    expect(shouldRetryQuery(0, 'boom')).toBe(true);
    expect(shouldRetryQuery(0, null)).toBe(true);
  });
});
