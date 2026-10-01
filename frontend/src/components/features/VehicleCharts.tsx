import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { ChartsSection } from './ChartsSection';
import { DASHBOARD_CHART_MONTHS } from '@/hooks/useDashboardCharts';
import { useVehicleCharts, useVehicles } from '@/hooks/useVehicles';
import { shouldShowVehicleCharts } from '@/lib/vehicleCharts';
import type { Vehicle } from '@/api/types/vehicle';

export interface VehicleChartsProps {
  vehicle: Pick<Vehicle, 'id' | 'ownership' | 'archivedAt'>;
}

/**
 * Grafici del solo veicolo nel suo dettaglio. Nascosti (e senza richieste) quando duplicherebbero
 * la dashboard: veicolo proprio, attivo e unico proprio (vedi `shouldShowVehicleCharts`).
 * Per un veicolo proprio attivo la scelta dipende dalla lista veicoli: finché non arriva non si
 * mostra nulla; se la lista fallisce si mostrano i grafici (l'accesso lo garantisce il backend).
 */
export function VehicleCharts({ vehicle }: VehicleChartsProps) {
  const { t } = useTranslation();
  const headingId = useId();
  const vehicles = useVehicles();

  const dependsOnList = vehicle.ownership === 'owned' && !vehicle.archivedAt;
  const show =
    !dependsOnList ||
    vehicles.isError ||
    (vehicles.data !== undefined && shouldShowVehicleCharts(vehicle, vehicles.data));

  const charts = useVehicleCharts(vehicle.id, DASHBOARD_CHART_MONTHS, { enabled: show });

  if (!show) return null;

  return (
    <ChartsSection
      headingId={headingId}
      title={t('vehicle.charts.title')}
      subtitle={t('vehicle.charts.subtitle', { months: DASHBOARD_CHART_MONTHS })}
      data={charts.data}
      isLoading={charts.isLoading}
      error={charts.error}
    />
  );
}
