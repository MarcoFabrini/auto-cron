import { useAuthStore } from '@/stores/useAuthStore';

/**
 * Membership "primaria" dell'utente corrente: owner/admin se presente,
 * altrimenti la prima. Source unica per derivare l'organizzazione attiva
 * mostrata in topbar / sidebar / impostazioni.
 */
export function useActiveMembership() {
  const user = useAuthStore((s) => s.user);
  return (
    user?.memberships.find((m) => m.role === 'owner' || m.role === 'admin') ??
    user?.memberships[0]
  );
}
