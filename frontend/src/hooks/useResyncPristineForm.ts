import { useEffect } from 'react';
import type { FieldValues, UseFormReturn } from 'react-hook-form';

/**
 * Le pagine di modifica mostrano il form appena c'è un dato in cache; il refetch in background (cache
 * più vecchia di staleTime) arriva dopo e di per sé non tocca un form già inizializzato: si salverebbe
 * una versione vecchia. Quando `source` (il dato) cambia, il form viene riallineato — ma solo se
 * l'utente non ha ancora toccato nulla, per non cancellare ciò che sta digitando.
 */
export function useResyncPristineForm<T extends FieldValues>(form: UseFormReturn<T>, source: unknown, values: T) {
  // isDirty è dietro un Proxy di react-hook-form: va letto in render per restare aggiornato.
  const { isDirty } = form.formState;

  useEffect(() => {
    if (source && !isDirty) form.reset(values);
    // Solo al cambio del dato: `values` è ricalcolato a ogni render e `isDirty` cambia mentre si digita.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [source]);
}
