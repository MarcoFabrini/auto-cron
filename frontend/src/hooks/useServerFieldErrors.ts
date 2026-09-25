import { useEffect } from 'react';
import type { FieldValues, Path, UseFormReturn } from 'react-hook-form';
import { ApiError } from '@/api/client';

/**
 * Riporta sui campi del form gli errori di validazione del backend (Problem Details `errors[]`).
 * Effetto, non render: `setError` aggiorna lo stato del form e non va chiamato mentre si renderizza.
 */
export function useServerFieldErrors<T extends FieldValues>(form: UseFormReturn<T>, error: unknown) {
  const { setError } = form;
  useEffect(() => {
    if (!(error instanceof ApiError) || !error.errors) return;
    for (const err of error.errors) {
      setError(err.field as Path<T>, { message: err.message });
    }
  }, [error, setError]);
}
