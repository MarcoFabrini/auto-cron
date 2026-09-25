import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  createExpense,
  deleteExpense,
  getExpense,
  listExpenses,
  updateExpense,
} from '@/api/endpoints/expenses';
import type { CreateExpenseDto, UpdateExpenseDto } from '@/api/types/expense';
import { invalidateVehicleStats } from '@/hooks/useVehicles';

export const expenseKeys = {
  all: ['expenses'] as const,
  byVehicle: (vehicleId: number) => [...expenseKeys.all, 'vehicle', vehicleId] as const,
  detail: (id: number) => [...expenseKeys.all, 'detail', id] as const,
};

export function useExpenses(vehicleId: number) {
  return useQuery({
    queryKey: expenseKeys.byVehicle(vehicleId),
    queryFn: () => listExpenses(vehicleId),
    enabled: vehicleId > 0,
  });
}

export function useExpense(id: number) {
  return useQuery({
    queryKey: expenseKeys.detail(id),
    queryFn: () => getExpense(id),
    enabled: id > 0,
  });
}

export function useCreateExpense() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: CreateExpenseDto) => createExpense(body),
    onSuccess: (_data, vars) => {
      void qc.invalidateQueries({ queryKey: expenseKeys.byVehicle(vars.vehicleId) });
      invalidateVehicleStats(qc, vars.vehicleId);
    },
  });
}

export function useUpdateExpense(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: UpdateExpenseDto) => updateExpense(id, body),
    onSuccess: (_data, vars) => {
      void qc.invalidateQueries({ queryKey: expenseKeys.detail(id) });
      void qc.invalidateQueries({ queryKey: expenseKeys.byVehicle(vars.vehicleId) });
      invalidateVehicleStats(qc, vars.vehicleId);
    },
  });
}

export function useDeleteExpense(vehicleId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteExpense(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: expenseKeys.byVehicle(vehicleId) });
      invalidateVehicleStats(qc, vehicleId);
    },
  });
}
