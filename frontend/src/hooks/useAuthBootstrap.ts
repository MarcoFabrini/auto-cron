import { useEffect } from 'react';
import { authFetch } from '@/api/client';
import { useAuthStore, type User } from '@/stores/useAuthStore';

/**
 * useAuthBootstrap — al mount tenta refresh sessione via cookie HttpOnly.
 *
 * Risolve il bug "refresh page = logout": il Zustand store è in-memory,
 * quindi un page reload azzera l'access token. Ma il refresh_token cookie
 * sopravvive. Questo hook prova /api/auth/refresh; se valido ricarica /me
 * e ripristina la sessione, altrimenti marca unauthenticated.
 *
 * Esegue una sola volta all'avvio app.
 */
export function useAuthBootstrap() {
  const status = useAuthStore((s) => s.status);

  useEffect(() => {
    let cancelled = false;

    async function bootstrap() {
      try {
        const data = await authFetch<{ access_token: string }>('/api/auth/refresh', {
          method: 'POST',
          headers: { 'X-Client-Type': 'web' },
          skipRefresh: true,
        });
        if (cancelled) return;
        useAuthStore.getState().setAccessToken(data.access_token);
        const me = await authFetch<User>('/api/auth/me');
        if (cancelled) return;
        useAuthStore.getState().setAuthenticated(data.access_token, me);
      } catch {
        if (!cancelled) useAuthStore.getState().setUnauthenticated();
      }
    }

    void bootstrap();
    return () => {
      cancelled = true;
    };
  }, []);

  return status;
}
