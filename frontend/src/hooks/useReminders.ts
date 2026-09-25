import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  completeReminder,
  createReminder,
  deleteReminder,
  getReminder,
  listReminders,
  listUpcomingReminders,
  updateReminder,
} from '@/api/endpoints/reminders';
import type { CreateReminderDto, UpdateReminderDto } from '@/api/types/reminder';

export const reminderKeys = {
  all: ['reminders'] as const,
  byVehicle: (vehicleId: number) => [...reminderKeys.all, 'vehicle', vehicleId] as const,
  detail: (id: number) => [...reminderKeys.all, 'detail', id] as const,
  upcoming: (days: number, limit: number) => [...reminderKeys.all, 'upcoming', days, limit] as const,
};

export function useReminders(vehicleId: number) {
  return useQuery({
    queryKey: reminderKeys.byVehicle(vehicleId),
    queryFn: () => listReminders(vehicleId),
    enabled: vehicleId > 0,
  });
}

/** Scadenze a data in arrivo su tutti i veicoli dell'organizzazione (dashboard). */
export function useUpcomingReminders(days = 30, limit = 5) {
  return useQuery({
    queryKey: reminderKeys.upcoming(days, limit),
    queryFn: () => listUpcomingReminders(days, limit),
  });
}

export function useReminder(id: number) {
  return useQuery({
    queryKey: reminderKeys.detail(id),
    queryFn: () => getReminder(id),
    enabled: id > 0,
  });
}

export function useCreateReminder() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: CreateReminderDto) => createReminder(body),
    onSuccess: (_data, vars) => {
      void qc.invalidateQueries({ queryKey: reminderKeys.byVehicle(vars.vehicleId) });
    },
  });
}

export function useUpdateReminder(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (body: UpdateReminderDto) => updateReminder(id, body),
    onSuccess: (_data, vars) => {
      void qc.invalidateQueries({ queryKey: reminderKeys.detail(id) });
      void qc.invalidateQueries({ queryKey: reminderKeys.byVehicle(vars.vehicleId) });
    },
  });
}

export function useCompleteReminder(vehicleId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => completeReminder(id),
    onSuccess: (data) => {
      void qc.invalidateQueries({ queryKey: reminderKeys.detail(data.id) });
      void qc.invalidateQueries({ queryKey: reminderKeys.byVehicle(vehicleId) });
    },
  });
}

export function useDeleteReminder(vehicleId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => deleteReminder(id),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: reminderKeys.byVehicle(vehicleId) });
    },
  });
}
