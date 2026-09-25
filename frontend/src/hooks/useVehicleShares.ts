import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { authFetch } from '@/api/client';

const JSON_HEADERS = { 'Content-Type': 'application/json' };

export type ShareRole = 'viewer' | 'editor' | 'admin';

interface ShareUser {
  id: number;
  email: string;
  firstName: string;
  lastName: string;
}

export interface VehicleShare {
  id: number;
  role: ShareRole;
  acceptedAt: string | null;
  createdAt: string;
  user: ShareUser;
  invitedBy?: ShareUser | null;
}

const sharesKey = (vehicleId: number) => ['vehicles', vehicleId, 'shares'] as const;

/**
 * Share del veicolo: quello `admin` è il proprietario (chi l'ha creato, se
 * member), i `viewer` sono le condivisioni in sola lettura.
 */
export function useVehicleShares(vehicleId: number, enabled = true) {
  return useQuery({
    queryKey: sharesKey(vehicleId),
    queryFn: () => authFetch<VehicleShare[]>(`/api/vehicles/${vehicleId}/shares`),
    enabled,
  });
}

/** Membro dell'organizzazione con cui si può condividere il veicolo (solo nome: niente email). */
export interface ShareCandidate {
  id: number;
  firstName: string;
  lastName: string;
}

const candidatesKey = (vehicleId: number) => ['vehicles', vehicleId, 'share-candidates'] as const;

/**
 * Membri dell'org selezionabili per la condivisione: accettati, ruolo member, non
 * chi condivide e non chi ha già uno share (owner/admin vedono già tutto).
 */
export function useShareCandidates(vehicleId: number, enabled = true) {
  return useQuery({
    queryKey: candidatesKey(vehicleId),
    queryFn: () => authFetch<ShareCandidate[]>(`/api/vehicles/${vehicleId}/share-candidates`),
    enabled,
  });
}

/** Condivide il veicolo in sola lettura con uno o più membri dell'org, scelti per id. */
export function useCreateShare(vehicleId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (payload: { userIds: number[] }) =>
      authFetch<VehicleShare[]>(`/api/vehicles/${vehicleId}/shares`, {
        method: 'POST',
        headers: JSON_HEADERS,
        body: JSON.stringify(payload),
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: sharesKey(vehicleId) });
      void qc.invalidateQueries({ queryKey: candidatesKey(vehicleId) });
    },
  });
}

/** Revoca una condivisione. */
export function useRevokeShare(vehicleId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (shareId: number) =>
      authFetch<void>(`/api/vehicles/${vehicleId}/shares/${shareId}`, { method: 'DELETE' }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: sharesKey(vehicleId) });
      void qc.invalidateQueries({ queryKey: candidatesKey(vehicleId) });
    },
  });
}
