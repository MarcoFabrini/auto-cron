import { useCallback, useEffect } from 'react';
import { authFetch, refreshAccessToken } from '@/api/client';
import { useAuthStore, type User } from '@/stores/useAuthStore';

/**
 * useAuthBootstrap — al mount tenta refresh sessione via cookie HttpOnly.
 *
 * Risolve il bug "refresh page = logout": il Zustand store è in-memory,
 * quindi un page reload azzera l'access token. Ma il refresh_token cookie
 * sopravvive. Questo hook prova /api/auth/refresh; se valido ricarica /me
 * e ripristina la sessione.
 *
 * Solo un rifiuto esplicito del server (400/401) vale come "no sessione". Se il server è
 * irraggiungibile (offline, riavvio, 5xx) lo stato diventa 'unreachable' e l'utente può riprovare
 * senza perdere la sessione.
 *
 * Usa `refreshAccessToken` (coalescente): in StrictMode il doppio mount non manda due refresh
 * concorrenti, che col refresh rotante farebbero fallire il secondo.
 */
export function useAuthBootstrap() {
  const status = useAuthStore((s) => s.status);

  const bootstrap = useCallback(async (isCancelled: () => boolean) => {
    const result = await refreshAccessToken();
    if (isCancelled()) return;
    if (result === 'invalid') {
      useAuthStore.getState().setUnauthenticated();
      return;
    }
    if (result === 'unavailable') {
      useAuthStore.getState().setUnreachable();
      return;
    }
    try {
      const me = await authFetch<User>('/api/auth/me');
      if (isCancelled()) return;
      const token = useAuthStore.getState().accessToken;
      if (token) useAuthStore.getState().setAuthenticated(token, me);
    } catch (err) {
      if (isCancelled()) return;
      const status = (err as { status?: number }).status;
      if (status === 401) useAuthStore.getState().setUnauthenticated();
      else useAuthStore.getState().setUnreachable();
    }
  }, []);

  useEffect(() => {
    let cancelled = false;
    void bootstrap(() => cancelled);
    return () => {
      cancelled = true;
    };
  }, [bootstrap]);

  const retry = useCallback(() => {
    useAuthStore.setState({ status: 'loading' });
    void bootstrap(() => false);
  }, [bootstrap]);

  return { status, retry };
}
