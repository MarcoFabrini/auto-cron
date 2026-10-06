import type { MemberRole } from '@/hooks/useOrganizationMembers';

/** Dal più al meno privilegiato: l'ordine in cui il Select mostra i ruoli. */
export const MEMBER_ROLES: readonly MemberRole[] = ['owner', 'admin', 'member'];

/** Type guard per il valore (stringa) che arriva dal Select, senza cast. */
export function isMemberRole(value: string): value is MemberRole {
  return MEMBER_ROLES.some((r) => r === value);
}

/**
 * Ruoli a cui `actorRole` può portare il membro `targetRole` (il ruolo attuale escluso). Vuoto = nessun
 * controllo, solo il badge di sola lettura. Rispecchia la matrice del backend (che resta l'autorità):
 * - owner: potere assoluto su chiunque; su un owner (anche sé stesso) solo se ne restano altri (`ownerCount` >= 2);
 * - admin: solo promuovere un `member` ad `admin`; mai se stesso, altri admin o owner;
 * - member: niente.
 */
export function assignableRoles(
  actorRole: MemberRole,
  targetRole: MemberRole,
  isSelf: boolean,
  ownerCount: number,
): MemberRole[] {
  if (actorRole === 'owner') {
    if (targetRole === 'owner' && ownerCount < 2) return [];
    return MEMBER_ROLES.filter((r) => r !== targetRole);
  }
  if (actorRole === 'admin' && !isSelf && targetRole === 'member') return ['admin'];
  return [];
}

/** Direzione del cambio, per scegliere il testo di conferma. */
export type RoleChangeKind = 'to_owner' | 'owner_demoted' | 'admin_to_member' | 'member_to_admin';

export function roleChangeKind(from: MemberRole, to: MemberRole): RoleChangeKind {
  if (to === 'owner') return 'to_owner';
  if (from === 'owner') return 'owner_demoted';
  return from === 'admin' ? 'admin_to_member' : 'member_to_admin';
}

/** Un declassamento (ruolo meno privilegiato): la conferma è distruttiva e gli inviti pendenti cadono. */
export function isDemotion(from: MemberRole, to: MemberRole): boolean {
  return MEMBER_ROLES.indexOf(to) > MEMBER_ROLES.indexOf(from);
}
