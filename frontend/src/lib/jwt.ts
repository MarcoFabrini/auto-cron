/**
 * Claim `active_org_id` dell'access token (senza verificare la firma: è solo per scegliere cosa
 * mostrare; l'autorizzazione vera la fa sempre il backend). null se assente o token malformato.
 */
export function activeOrgIdFromToken(token: string | null | undefined): number | null {
  if (!token) return null;
  try {
    const payload = token.split('.')[1];
    if (!payload) return null;
    const json = atob(payload.replace(/-/g, '+').replace(/_/g, '/'));
    const id = (JSON.parse(json) as { active_org_id?: unknown }).active_org_id;
    const n = typeof id === 'string' ? Number(id) : id;
    return typeof n === 'number' && Number.isInteger(n) && n > 0 ? n : null;
  } catch {
    return null;
  }
}
