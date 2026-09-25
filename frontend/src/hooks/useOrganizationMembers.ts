import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { authFetch } from '@/api/client';

const JSON_HEADERS = { 'Content-Type': 'application/json' };

export type MemberRole = 'owner' | 'admin' | 'member';

export interface OrgMember {
  id: number;
  role: MemberRole;
  acceptedAt: string | null;
  user: { id: number; email: string; firstName: string; lastName: string };
}

export interface OrgInvitation {
  id: number;
  email: string;
  role: Exclude<MemberRole, 'owner'>;
  createdAt: string;
}

const membersKey = (orgId: number) => ['organizations', orgId, 'members'] as const;
const invitationsKey = (orgId: number) => ['organizations', orgId, 'invitations'] as const;

/** Membri (accettati) dell'organizzazione. Owner/admin only (403 altrimenti). */
export function useMembers(orgId: number, enabled = true) {
  return useQuery({
    queryKey: membersKey(orgId),
    queryFn: () => authFetch<OrgMember[]>(`/api/organizations/${orgId}/members`),
    enabled,
  });
}

/** Inviti pendenti dell'organizzazione (email ancora da accettare). */
export function useInvitations(orgId: number, enabled = true) {
  return useQuery({
    queryKey: invitationsKey(orgId),
    queryFn: () => authFetch<OrgInvitation[]>(`/api/organizations/${orgId}/invitations`),
    enabled,
  });
}

/** Invita un'email (anche non registrata: l'utente si crea l'account in accettazione). */
export function useInviteMember(orgId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { email: string; role: Exclude<MemberRole, 'owner'> }) =>
      authFetch<OrgInvitation>(`/api/organizations/${orgId}/members`, {
        method: 'POST',
        headers: JSON_HEADERS,
        body: JSON.stringify(payload),
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: invitationsKey(orgId) });
      void qc.invalidateQueries({ queryKey: membersKey(orgId) });
    },
  });
}

/** Revoca un invito pendente. */
export function useRevokeInvitation(orgId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (invitationId: number) =>
      authFetch<void>(`/api/organizations/${orgId}/invitations/${invitationId}`, { method: 'DELETE' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: invitationsKey(orgId) }),
  });
}

/** Rimuove un membro accettato (owner/admin). L'owner non è rimovibile. */
export function useRemoveMember(orgId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (memberId: number) =>
      authFetch<void>(`/api/organizations/${orgId}/members/${memberId}`, { method: 'DELETE' }),
    onSuccess: () => qc.invalidateQueries({ queryKey: membersKey(orgId) }),
  });
}
