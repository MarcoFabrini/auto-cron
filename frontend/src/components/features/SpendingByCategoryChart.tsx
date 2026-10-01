import { useTranslation } from 'react-i18next';
import type { TFunction } from 'i18next';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { ChartCard } from './ChartCard';
import { CHART_COLORS, TOOLTIP_CONTENT_STYLE, chartColor } from './chartTheme';
import { REST_CATEGORY, type CategorySlice } from '@/lib/dashboardCharts';
import { formatCurrency } from '@/lib/format';

export interface SpendingByCategoryChartProps {
  /** Già raggruppate (prime categorie + "Altre categorie"), importo decrescente. */
  slices: CategorySlice[];
}

/** Rifornimenti, manutenzioni e spese per categoria hanno etichette in sezioni diverse. */
function categoryLabel(t: TFunction, category: string): string {
  if (category === 'fuel' || category === 'maintenance' || category === REST_CATEGORY) {
    return t(`dashboard.charts.category.${category}`);
  }
  return t(`expense.category_options.${category}`, { defaultValue: category });
}

/** "Altre categorie" sempre nel colore neutro (l'ultimo della palette), le altre in ordine. */
function sliceColor(slice: CategorySlice, index: number): string {
  return slice.category === REST_CATEGORY ? chartColor(CHART_COLORS.length - 1) : chartColor(index);
}

/**
 * Distribuzione delle spese della finestra ad anello. La legenda è una lista con gli importi
 * sotto il grafico invece della legenda di recharts: su mobile resta leggibile e non copre l'anello.
 */
export function SpendingByCategoryChart({ slices }: SpendingByCategoryChartProps) {
  const { t } = useTranslation();
  const data = slices.map((slice, i) => ({
    name: categoryLabel(t, slice.category),
    amount: slice.amount,
    color: sliceColor(slice, i),
  }));

  return (
    <ChartCard
      title={t('dashboard.charts.spending_by_category')}
      emptyMessage={t('dashboard.charts.empty.categories')}
      isEmpty={slices.length === 0}
      footer={
        <ul className="mt-3 grid grid-cols-1 gap-x-4 gap-y-1.5 text-sm sm:grid-cols-2">
          {data.map((entry) => (
            <li key={entry.name} className="flex min-w-0 items-center gap-2">
              <span aria-hidden className="size-2.5 shrink-0 rounded-full" style={{ backgroundColor: entry.color }} />
              <span className="min-w-0 flex-1 truncate text-muted-foreground">{entry.name}</span>
              <span className="font-medium tabular-nums">{formatCurrency(entry.amount)}</span>
            </li>
          ))}
        </ul>
      }
    >
      <ResponsiveContainer width="100%" height="100%">
        <PieChart>
          <Tooltip contentStyle={TOOLTIP_CONTENT_STYLE} itemStyle={{ color: 'hsl(var(--popover-foreground))' }} formatter={(value) => formatCurrency(Number(value))} />
          <Pie data={data} dataKey="amount" nameKey="name" innerRadius="55%" outerRadius="85%" paddingAngle={2} stroke="hsl(var(--card))">
            {data.map((entry) => (
              <Cell key={entry.name} fill={entry.color} />
            ))}
          </Pie>
        </PieChart>
      </ResponsiveContainer>
    </ChartCard>
  );
}
