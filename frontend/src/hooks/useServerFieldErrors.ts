import { useEffect, useMemo } from 'react';
import type { FieldValues, Path, UseFormReturn } from 'react-hook-form';
import { ApiError } from '@/api/client';

/** Campi che nel form esistono come valore ma non hanno un input visibile dove mostrare l'errore. */
const HIDDEN_FIELDS = new Set(['vehicleId']);

/**
 * Riporta sui campi del form gli errori di validazione del backend (Problem Details `errors[]`).
 * Effetto, non render: `setError` aggiorna lo stato del form e non va chiamato mentre si renderizza.
 *
 * Ritorna `hasUnmapped`: true se il backend ha rifiutato qualcosa che non ha un campo visibile
 * (es. veicolo non valido o archiviato). Il form deve allora mostrare un errore generico, altrimenti
 * il submit fallirebbe senza alcun messaggio.
 */
export function useServerFieldErrors<T extends FieldValues>(form: UseFormReturn<T>, error: unknown): { hasUnmapped: boolean } {
  const { setError, getValues } = form;

  const fieldErrors = useMemo(() => (error instanceof ApiError && error.errors ? error.errors : []), [error]);
  const isVisible = (field: string) => !HIDDEN_FIELDS.has(field) && field in (getValues() as Record<string, unknown>);

  useEffect(() => {
    for (const err of fieldErrors) {
      if (isVisible(err.field)) setError(err.field as Path<T>, { message: err.message });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [fieldErrors, setError]);

  return { hasUnmapped: fieldErrors.some((err) => !isVisible(err.field)) };
}
