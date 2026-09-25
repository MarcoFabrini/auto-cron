import { useMutation, useQuery, useQueryClient, type QueryClient } from '@tanstack/react-query';
import {
  archiveVehicle,
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

/** Statistiche veicolo (km attuali, consumo, costi). Stessa chiave della dashboard. */
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
    onSuccess: () => {
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
