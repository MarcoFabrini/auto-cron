import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createMaintenance,
  deleteMaintenance,
  getMaintenance,
  listMaintenances,
  updateMaintenance,
} from '@/api/endpoints/maintenances';
import type {
  CreateMaintenanceDto,
  UpdateMaintenanceDto,
} from '@/api/types/maintenance';
import { invalidateVehicleStats } from '@/hooks/useVehicles';

export const maintenanceKeys = {
  all: ['maintenances'] as const,
  byVehicle: (vehicleId: number) => [...maintenanceKeys.all, 'vehicle', vehicleId] as const,
  detail: (id: number) => [...maintenanceKeys.all, 'detail', id] as const,
};

export function useMaintenances(vehicleId: number) {
  return useQuery({
    queryKey: maintenanceKeys.byVehicle(vehicleId),
    queryFn: () => listMaintenances(vehicleId),
    enabled: vehicleId > 0,
  });
}

export function useMaintenance(id: number) {
  return useQuery({
    queryKey: maintenanceKeys.detail(id),
    queryFn: () => getMaintenance(id),
    enabled: id > 0,
  });
}

export function useCreateMaintenance() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: CreateMaintenanceDto) => createMaintenance(body),
    onSuccess: (_data, vars) => {
      void qc.invalidateQueries({ queryKey: maintenanceKeys.byVehicle(vars.vehicleId) });
      invalidateVehicleStats(qc, vars.vehicleId);
    },
  });
}

export function useUpdateMaintenance(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: UpdateMaintenanceDto) => updateMaintenance(id, body),
    onSuccess: (_data, vars) => {
      void qc.invalidateQueries({ queryKey: maintenanceKeys.detail(id) });
      void qc.invalidateQueries({ queryKey: maintenanceKeys.byVehicle(vars.vehicleId) });
      invalidateVehicleStats(qc, vars.vehicleId);
    },
  });
}

export function useDeleteMaintenance(vehicleId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteMaintenance(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: maintenanceKeys.byVehicle(vehicleId) });
      invalidateVehicleStats(qc, vehicleId);
    },
  });
}
