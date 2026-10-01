import { useTranslation } from 'react-i18next';
import { ChartsSection } from './ChartsSection';
import { DASHBOARD_CHART_MONTHS, useDashboardCharts } from '@/hooks/useDashboardCharts';

/**
 * Sezione grafici della dashboard: spese mensili, spese per categoria, km percorsi e consumo
 * degli ultimi 12 mesi. Solo i veicoli propri dell'utente (archiviati esclusi): il filtro lo fa
 * il backend, la pagina mostra la sezione solo a chi ha almeno un veicolo proprio.
 */
export function DashboardCharts() {
  const { t } = useTranslation();
  const { data, isLoading, error } = useDashboardCharts();

  return (
    <ChartsSection
      headingId="dashboard-charts-title"
      title={t('dashboard.charts.title')}
      subtitle={t('dashboard.charts.subtitle', { months: DASHBOARD_CHART_MONTHS })}
      data={data}
      isLoading={isLoading}
      error={error}
    />
  );
}
