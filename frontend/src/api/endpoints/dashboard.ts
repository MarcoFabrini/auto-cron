import { authFetch } from '@/api/client';
import { dashboardChartsSchema, type DashboardCharts } from '@/api/types/dashboardCharts';

/** Grafici della dashboard degli ultimi `months` mesi (solo veicoli propri, lo filtra il backend). */
export async function getDashboardCharts(months: number): Promise<DashboardCharts> {
  const raw = await authFetch<unknown>(`/api/dashboard/charts?months=${months}`);
  return dashboardChartsSchema.parse(raw);
}
