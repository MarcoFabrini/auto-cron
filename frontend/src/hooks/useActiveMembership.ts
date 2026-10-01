import { activeOrgIdFromToken } from '@/lib/jwt';
import { useAuthStore } from '@/stores/useAuthStore';

/**
 * Membership dell'organizzazione ATTIVA, cioè quella del claim `active_org_id` dell'access token:
 * è l'org su cui opera davvero ogni chiamata API. Se il claim manca o non corrisponde a nessuna
 * membership: owner/admin se presente, altrimenti la prima. Source unica per topbar / sidebar /
 * impostazioni.
 */
export function useActiveMembership() {
  const user = useAuthStore((s) => s.user);
  const accessToken = useAuthStore((s) => s.accessToken);
  const activeOrgId = activeOrgIdFromToken(accessToken);
  return (
    user?.memberships.find((m) => m.organization.id === activeOrgId) ??
    user?.memberships.find((m) => m.role === 'owner' || m.role === 'admin') ??
    user?.memberships[0]
  );
}
