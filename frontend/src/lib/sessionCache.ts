import type { QueryClient } from '@tanstack/react-query';
import { useAuthStore } from '@/stores/useAuthStore';
import { activeOrgIdFromToken } from '@/lib/jwt';

type Session = { user: { id: number } | null; accessToken: string | null };

/**
 * True se la cache delle query non appartiene più alla sessione corrente:
 * - l'utente è uscito o è cambiato (cache di un altro utente mai visibile);
 * - l'organizzazione attiva dell'access token passa da un valore a un ALTRO valore (switch-org, un
 *   refresh in un'altra scheda che ha adottato un'altra org, qualsiasi sostituzione del token): i
 *   dati in cache sono dell'org precedente. Da/verso null no: è il token che si assesta (login,
 *   bootstrap), non un cambio di organizzazione.
 */
export function sessionCacheIsStale(next: Session, prev: Session): boolean {
  if (prev.user && (!next.user || next.user.id !== prev.user.id)) return true;

  const prevOrg = activeOrgIdFromToken(prev.accessToken);
  const nextOrg = activeOrgIdFromToken(next.accessToken);
  return prevOrg !== null && nextOrg !== null && prevOrg !== nextOrg;
}

/**
 * Svuota la cache di `queryClient` quando la sessione cambia (vedi {@link sessionCacheIsStale}),
 * in un unico punto invece che nei singoli hook. Ritorna la funzione per smettere di ascoltare.
 */
export function clearQueryCacheOnSessionChange(queryClient: Pick<QueryClient, 'clear'>): () => void {
  return useAuthStore.subscribe((state, prev) => {
    if (sessionCacheIsStale(state, prev)) queryClient.clear();
  });
}
