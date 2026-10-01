import { useMutation, useQuery, useQueryClient, type QueryClient } from '@tanstack/react-query';
import {
  archiveVehicle,
  unarchiveVehicle,
  createVehicle,
  deleteVehicle,
  getVehicle,
  getVehicleStats,
  listVehicles,
  updateVehicle,
} from '@/api/endpoints/vehicles';
import type { CreateVehicleDto, UpdateVehicleDto } from '@/api/types/vehicle';

/**
 * Query keys factory — single source of truth per cache invalidation.
 */
export const vehicleKeys = {
  all: ['vehicles'] as const,
  lists: () => [...vehicleKeys.all, 'list'] as const,
  details: () => [...vehicleKeys.all, 'detail'] as const,
  detail: (id: number) => [...vehicleKeys.details(), id] as const,
};

export function useVehicles() {
  return useQuery({
    queryKey: vehicleKeys.lists(),
    queryFn: listVehicles,
  });
}

export function useVehicle(id: number) {
  return useQuery({
    queryKey: vehicleKeys.detail(id),
    queryFn: () => getVehicle(id),
    enabled: id > 0,
  });
}

/**
 * Se l'utente può modificare il veicolo e i suoi dati (record, allegati, promemoria):
 * `undefined` finché non è noto, `false` per un veicolo condiviso in sola lettura.
 * Il backend applica comunque i voter: qui serve solo a non mostrare azioni che darebbero 403.
 */
export function useCanEditVehicle(vehicleId: number): boolean | undefined {
  const { data } = useVehicle(vehicleId);
  return data?.permissions.canEdit;
}

/** Chiave delle statistiche di un veicolo (km attuali, consumi, costi): condivisa da dettaglio, dashboard e badge promemoria. */
export const vehicleStatsKey = (id: number) => [...vehicleKeys.detail(id), 'stats'] as const;

/** Da chiamare quando cambia un dato che alimenta le statistiche (rifornimenti, manutenzioni, spese). */
export function invalidateVehicleStats(qc: QueryClient, id: number) {
  void qc.invalidateQueries({ queryKey: vehicleStatsKey(id) });
}

export function useVehicleStats(id: number, options: { enabled?: boolean } = {}) {
  return useQuery({
    queryKey: vehicleStatsKey(id),
    queryFn: () => getVehicleStats(id),
    enabled: id > 0 && (options.enabled ?? true),
  });
}

export function useCreateVehicle() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: CreateVehicleDto) => createVehicle(body),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: vehicleKeys.lists() });
    },
  });
}

export function useUpdateVehicle(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: UpdateVehicleDto) => updateVehicle(id, body),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: vehicleKeys.detail(id) });
      void qc.invalidateQueries({ queryKey: vehicleKeys.lists() });
    },
  });
}

export function useDeleteVehicle() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteVehicle(id),
    onSuccess: (_data, id) => {
      void qc.invalidateQueries({ queryKey: vehicleKeys.lists() });
      qc.removeQueries({ queryKey: vehicleKeys.detail(id) });
      qc.removeQueries({ queryKey: vehicleStatsKey(id) });
      // Il veicolo cancellato porta via i figli (cascade): le loro liste in cache sono stale.
      // Radici letterali per non creare import circolari con gli hook dei figli.
      for (const root of ['maintenances', 'refuelings', 'expenses', 'reminders', 'attachments']) {
        void qc.invalidateQueries({ queryKey: [root] });
      }
    },
  });
}

export function useUnarchiveVehicle() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => unarchiveVehicle(id),
    onSuccess: (_data, id) => {
      void qc.invalidateQueries({ queryKey: vehicleKeys.detail(id) });
      void qc.invalidateQueries({ queryKey: vehicleKeys.lists() });
    },
  });
}

export function useArchiveVehicle() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => archiveVehicle(id),
    onSuccess: (_data, id) => {
      void qc.invalidateQueries({ queryKey: vehicleKeys.detail(id) });
      void qc.invalidateQueries({ queryKey: vehicleKeys.lists() });
    },
  });
}
