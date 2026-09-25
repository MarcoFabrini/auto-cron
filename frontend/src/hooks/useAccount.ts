import { useMutation, useQuery } from '@tanstack/react-query';
import { authFetch } from '@/api/client';
import { useAuthStore, type User } from '@/stores/useAuthStore';

const JSON_HEADERS = { 'Content-Type': 'application/json' };

interface ProfilePayload {
  email: string;
  firstName: string;
  lastName: string;
  locale: 'it' | 'en';
}

interface AuthLikeResponse {
  access_token: string;
  user: Omit<User, 'memberships'>;
}

/** Aggiorna profilo (nome/email/locale). Riemette JWT (l'email è l'identifier) + ricarica /me. */
export function useUpdateProfile() {
  return useMutation({
    mutationFn: async (payload: ProfilePayload) => {
      const res = await authFetch<AuthLikeResponse>('/api/auth/profile', {
        method: 'PUT',
        headers: JSON_HEADERS,
        body: JSON.stringify(payload),
      });
      useAuthStore.getState().setAccessToken(res.access_token);
      const me = await authFetch<User>('/api/auth/me');
      useAuthStore.getState().setUser(me);
      return me;
    },
  });
}

interface ChangePasswordPayload {
  currentPassword: string;
  newPassword: string;
}

/** Cambia password: verifica corrente, ruota la sessione (nuovo access_token + cookie). */
export function useChangePassword() {
  return useMutation({
    mutationFn: async (payload: ChangePasswordPayload) => {
      const res = await authFetch<AuthLikeResponse>('/api/auth/password', {
        method: 'PUT',
        headers: JSON_HEADERS,
        body: JSON.stringify(payload),
      });
      useAuthStore.getState().setAccessToken(res.access_token);
    },
  });
}

interface UpdateOrganizationPayload {
  id: number;
  name: string;
}

/** Rinomina organizzazione (owner/admin). Ricarica /me per aggiornare le membership nello store. */
export function useUpdateOrganization() {
  return useMutation({
    mutationFn: async (payload: UpdateOrganizationPayload) => {
      await authFetch(`/api/organizations/${payload.id}`, {
        method: 'PUT',
        headers: JSON_HEADERS,
        body: JSON.stringify({ name: payload.name }),
      });
      const me = await authFetch<User>('/api/auth/me');
      useAuthStore.getState().setUser(me);
      return me;
    },
  });
}

/** Richiede email di reset. Risposta sempre 200 (no enumeration). */
export function useForgotPassword() {
  return useMutation({
    mutationFn: (payload: { email: string }) =>
      authFetch<{ status: string }>('/api/auth/forgot-password', {
        method: 'POST',
        headers: JSON_HEADERS,
        body: JSON.stringify(payload),
        skipRefresh: true,
      }),
  });
}

/** Reimposta la password con il token dal link email. */
export function useResetPassword() {
  return useMutation({
    mutationFn: (payload: { token: string; password: string }) =>
      authFetch<void>('/api/auth/reset-password', {
        method: 'POST',
        headers: JSON_HEADERS,
        body: JSON.stringify(payload),
        skipRefresh: true,
      }),
  });
}

/** Conferma l'indirizzo email col token dal link (pubblico, no sessione). */
export function useVerifyEmail() {
  return useMutation({
    mutationFn: (payload: { token: string }) =>
      authFetch<void>('/api/auth/verify-email', {
        method: 'POST',
        headers: JSON_HEADERS,
        body: JSON.stringify(payload),
        skipRefresh: true,
      }),
  });
}

/** Rinvia l'email di verifica all'utente corrente. Ricarica /me se nel frattempo è verificato. */
export function useResendVerification() {
  return useMutation({
    mutationFn: () =>
      authFetch<void>('/api/auth/resend-verification', { method: 'POST' }),
  });
}

export interface InvitationPreview {
  organizationName: string;
  email: string;
  role: 'owner' | 'admin' | 'member';
  accountExists: boolean;
}

/** Anteprima invito dal token (pubblico): org, email, ruolo, se esiste già un account. */
export function useInvitationPreview(token: string) {
  return useQuery({
    queryKey: ['invitation', token],
    queryFn: () => authFetch<InvitationPreview>(`/api/auth/invitation/${token}`, { skipRefresh: true }),
    enabled: token.length > 0,
    retry: false,
  });
}

/** Accetta un invito (utente esistente, loggato). Ricarica /me per la nuova membership. */
export function useAcceptInvitation() {
  return useMutation({
    mutationFn: async (payload: { token: string }) => {
      await authFetch<void>('/api/auth/invitation/accept', {
        method: 'POST',
        headers: JSON_HEADERS,
        body: JSON.stringify(payload),
      });
      const me = await authFetch<User>('/api/auth/me');
      useAuthStore.getState().setUser(me);
      return me;
    },
  });
}

interface RegisterInvitedPayload {
  token: string;
  firstName: string;
  lastName: string;
  password: string;
  locale?: 'it' | 'en';
}

/** Registra un nuovo account dall'invito e accetta in un colpo: logga l'utente. */
export function useRegisterInvited() {
  const setAuthenticated = useAuthStore((s) => s.setAuthenticated);
  return useMutation({
    mutationFn: async (payload: RegisterInvitedPayload) => {
      const data = await authFetch<{ access_token: string }>('/api/auth/invitation/register', {
        method: 'POST',
        headers: JSON_HEADERS,
        body: JSON.stringify({ locale: 'it', ...payload }),
        skipRefresh: true,
      });
      useAuthStore.getState().setAccessToken(data.access_token);
      const me = await authFetch<User>('/api/auth/me');
      setAuthenticated(data.access_token, me);
      return me;
    },
  });
}
