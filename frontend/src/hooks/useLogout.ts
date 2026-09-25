import { useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { authFetch } from '@/api/client';
import { useAuthStore } from '@/stores/useAuthStore';

/**
 * useLogout — revoca refresh token server-side + pulisce store + cache,
 * poi redirect login.
 */
export function useLogout() {
  const navigate = useNavigate();
  const qc = useQueryClient();

  return async () => {
    try {
      await authFetch<void>('/api/auth/logout', { method: 'POST' });
    } catch {
      // Anche se la chiamata fallisce, deautentica comunque lato client
    }
    useAuthStore.getState().logout();
    qc.clear();
    navigate('/login', { replace: true });
  };
}
