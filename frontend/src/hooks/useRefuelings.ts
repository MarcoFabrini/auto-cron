import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createRefueling,
  deleteRefueling,
  getRefueling,
  listRefuelings,
  updateRefueling,
} from '@/api/endpoints/refuelings';
import type { CreateRefuelingDto, UpdateRefuelingDto } from '@/api/types/refueling';
import { invalidateVehicleStats } from '@/hooks/useVehicles';

export const refuelingKeys = {
  all: ['refuelings'] as const,
  byVehicle: (vehicleId: number) => [...refuelingKeys.all, 'vehicle', vehicleId] as const,
  detail: (id: number) => [...refuelingKeys.all, 'detail', id] as const,
};

export function useRefuelings(vehicleId: number) {
  return useQuery({
    queryKey: refuelingKeys.byVehicle(vehicleId),
    queryFn: () => listRefuelings(vehicleId),
    enabled: vehicleId > 0,
  });
}

export function useRefueling(id: number) {
  return useQuery({
    queryKey: refuelingKeys.detail(id),
    queryFn: () => getRefueling(id),
    enabled: id > 0,
  });
}

export function useCreateRefueling() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: CreateRefuelingDto) => createRefueling(body),
    onSuccess: (_data, vars) => {
      void qc.invalidateQueries({ queryKey: refuelingKeys.byVehicle(vars.vehicleId) });
      invalidateVehicleStats(qc, vars.vehicleId);
    },
  });
}

export function useUpdateRefueling(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: UpdateRefuelingDto) => updateRefueling(id, body),
    onSuccess: (_data, vars) => {
      void qc.invalidateQueries({ queryKey: refuelingKeys.detail(id) });
      void qc.invalidateQueries({ queryKey: refuelingKeys.byVehicle(vars.vehicleId) });
      invalidateVehicleStats(qc, vars.vehicleId);
    },
  });
}

export function useDeleteRefueling(vehicleId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteRefueling(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: refuelingKeys.byVehicle(vehicleId) });
      invalidateVehicleStats(qc, vehicleId);
    },
  });
}
