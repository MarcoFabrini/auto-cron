import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { ZodError } from 'zod';
import { Alert, Heading, Skeleton, Text } from '@/components/ui';
import { ConsumptionTrendChart } from './ConsumptionTrendChart';
import { KmDrivenChart } from './KmDrivenChart';
import { MonthlySpendingChart } from './MonthlySpendingChart';
import { SpendingByCategoryChart } from './SpendingByCategoryChart';
import { useApiErrorMessage } from '@/hooks/useApiErrorMessage';
import type { DashboardCharts } from '@/api/types/dashboardCharts';
import { groupCategories, toConsumptionRows, toKmDrivenRows, toMonthlySpendingRows } from '@/lib/dashboardCharts';
import { formatMonth, intlLocale } from '@/lib/format';

export interface ChartsSectionProps {
  /** Id dell'heading, unico nella pagina (la sezione ne è etichettata). */
  headingId: string;
  title: string;
  subtitle: string;
  data: DashboardCharts | undefined;
  isLoading: boolean;
  error: unknown;
}

/**
 * Sezione con i quattro grafici (spese mensili, spese per categoria, km percorsi, consumo),
 * condivisa da dashboard e dettaglio veicolo. Solo presentazione: i dati arrivano dal chiamante,
 * che sceglie il perimetro (veicoli propri o singolo veicolo).
 */
export function ChartsSection({ headingId, title, subtitle, data, isLoading, error }: ChartsSectionProps) {
  const { t, i18n } = useTranslation();
  const errorMessage = useApiErrorMessage();
  const locale = intlLocale(i18n.language);

  // Righe derivate dalla risposta, ricalcolate solo se cambiano i dati o la lingua.
  const series = useMemo(() => {
    if (!data) return null;
    const label = (month: string) => formatMonth(month, locale);
    return {
      spending: toMonthlySpendingRows(data, label),
      categories: groupCategories(data.spendingByCategory),
      km: toKmDrivenRows(data, label),
      consumption: toConsumptionRows(data, label),
      fuelTypes: data.fuelTypes,
    };
  }, [data, locale]);

  return (
    <section aria-labelledby={headingId} className="space-y-3">
      <div>
        <Heading level={4} as="h2" id={headingId}>
          {title}
        </Heading>
        <Text variant="muted" className="text-sm">
          {subtitle}
        </Text>
      </div>

      {error ? (
        <Alert variant="error">
          {error instanceof ZodError ? t('dashboard.charts.invalid_response') : errorMessage(error)}
        </Alert>
      ) : null}

      {isLoading ? (
        <div className="grid grid-cols-1 gap-3 md:gap-4 lg:grid-cols-2">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-72 w-full rounded-lg" />
          ))}
        </div>
      ) : null}

      {series ? (
        <div className="grid grid-cols-1 gap-3 md:gap-4 lg:grid-cols-2">
          <MonthlySpendingChart rows={series.spending} />
          <SpendingByCategoryChart slices={series.categories} />
          <KmDrivenChart rows={series.km} />
          <ConsumptionTrendChart rows={series.consumption} fuelTypes={series.fuelTypes} />
        </div>
      ) : null}
    </section>
  );
}
