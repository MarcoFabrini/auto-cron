import { useMutation, useQueryClient } from '@tanstack/react-query';
import { registrationKey } from '@/hooks/useRegistrationOpen';
import { authFetch } from '@/api/client';
import { useAuthStore, type User } from '@/stores/useAuthStore';

interface RegisterPayload {
  email: string;
  password: string;
  firstName: string;
  lastName: string;
  locale?: 'it' | 'en';
}

interface AuthResponse {
  access_token: string;
  user: Omit<User, 'memberships'>;
}

/**
 * useRegister — mutation register + auto-login (cookie già impostato da backend).
 */
export function useRegister() {
  const setAuthenticated = useAuthStore((s) => s.setAuthenticated);
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (payload: RegisterPayload) => {
      const data = await authFetch<AuthResponse>('/api/auth/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ locale: 'it', ...payload }),
        skipRefresh: true,
      });
      useAuthStore.getState().setAccessToken(data.access_token);
      const me = await authFetch<User>('/api/auth/me');
      setAuthenticated(data.access_token, me);
      // Da ora l'istanza ha un utente: registrazione libera chiusa.
      queryClient.setQueryData(registrationKey, { open: false });
      return me;
    },
  });
}
