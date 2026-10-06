import { useTranslation } from 'react-i18next';
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { ChartCard } from './ChartCard';
import {
  AXIS_STROKE,
  AXIS_TICK,
  GRID_STROKE,
  LEGEND_STYLE,
  TOOLTIP_CONTENT_STYLE,
  TOOLTIP_CURSOR,
  TOOLTIP_LABEL_STYLE,
  chartColor,
} from './chartTheme';
import { hasSpending, type MonthlySpendingRow } from '@/lib/dashboardCharts';
import { useFormat } from '@/hooks/useFormat';
import { usePrefersReducedMotion } from '@/hooks/usePrefersReducedMotion';

export interface MonthlySpendingChartProps {
  rows: MonthlySpendingRow[];
}

const SERIES = ['refuelings', 'maintenances', 'expenses'] as const;

/** Spese mensili impilate per sorgente: rifornimenti, manutenzioni, altre spese. */
export function MonthlySpendingChart({ rows }: MonthlySpendingChartProps) {
  const { t } = useTranslation();
  const fmt = useFormat();
  const reducedMotion = usePrefersReducedMotion();

  return (
    <ChartCard
      title={t('dashboard.charts.monthly_spending')}
      emptyMessage={t('dashboard.charts.empty.spending')}
      isEmpty={!hasSpending(rows)}
      table={{
        columns: [
          t('dashboard.charts.table.month'),
          ...SERIES.map((key) => t(`dashboard.charts.series.${key}`)),
          t('dashboard.charts.table.total'),
        ],
        rows: rows.map((row) => [
          row.label,
          ...SERIES.map((key) => fmt.currency(row[key])),
          fmt.currency(row.total),
        ]),
      }}
    >
      <ResponsiveContainer width="100%" height="100%">
        <BarChart data={rows} margin={{ top: 4, right: 4, bottom: 0, left: 0 }}>
          <CartesianGrid stroke={GRID_STROKE} strokeDasharray="3 3" vertical={false} />
          <XAxis dataKey="label" tick={AXIS_TICK} stroke={AXIS_STROKE} tickLine={false} interval="preserveStartEnd" minTickGap={12} />
          <YAxis tick={AXIS_TICK} stroke={AXIS_STROKE} tickLine={false} width={48} tickFormatter={(v: number) => fmt.decimal(v, 0)} />
          <Tooltip
            cursor={TOOLTIP_CURSOR}
            contentStyle={TOOLTIP_CONTENT_STYLE}
            labelStyle={TOOLTIP_LABEL_STYLE}
            formatter={(value) => fmt.currency(Number(value))}
          />
          <Legend wrapperStyle={LEGEND_STYLE} iconSize={10} />
          {SERIES.map((key, i) => (
            <Bar
              key={key}
              dataKey={key}
              name={t(`dashboard.charts.series.${key}`)}
              stackId="spending"
              fill={chartColor(i)}
              maxBarSize={32}
              isAnimationActive={!reducedMotion}
            />
          ))}
        </BarChart>
      </ResponsiveContainer>
    </ChartCard>
  );
}
