import { authFetch } from '@/api/client';

/**
 * Cambia l'organizzazione attiva della sessione: il backend riemette l'access token con il nuovo
 * claim `active_org_id` e ricorda la scelta sul refresh token. Ritorna il nuovo access token.
 * Unica implementazione, condivisa da `useSwitchOrganization` e da `useAcceptInvitation`.
 */
export async function switchOrganization(organizationId: number): Promise<string> {
  const res = await authFetch<{ access_token: string }>('/api/auth/switch-org', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ organizationId }),
  });
  return res.access_token;
}
