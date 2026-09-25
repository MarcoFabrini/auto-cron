import { useMutation } from '@tanstack/react-query';
import { authFetch } from '@/api/client';
import { useAuthStore, type User } from '@/stores/useAuthStore';

interface LoginPayload {
  email: string;
  password: string;
}

interface AuthResponse {
  access_token: string;
  user: Omit<User, 'memberships'>;
}

/**
 * useLogin — wrap mutation login con side-effect su auth store.
 *
 * Pattern: hook centralizza fetch + store update. Pagina chiama solo
 * mutate(payload) e usa isPending/error/onSuccess.
 */
export function useLogin() {
  const setAuthenticated = useAuthStore((s) => s.setAuthenticated);

  return useMutation({
    mutationFn: async (payload: LoginPayload) => {
      const data = await authFetch<AuthResponse>('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        skipRefresh: true,
      });
      // Access token serve già per la chiamata /me successiva
      useAuthStore.getState().setAccessToken(data.access_token);
      const me = await authFetch<User>('/api/auth/me');
      setAuthenticated(data.access_token, me);
      return me;
    },
  });
}
