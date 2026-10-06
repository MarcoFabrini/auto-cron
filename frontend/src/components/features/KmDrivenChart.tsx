import { useTranslation } from 'react-i18next';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { ChartCard } from './ChartCard';
import {
  AXIS_STROKE,
  AXIS_TICK,
  GRID_STROKE,
  TOOLTIP_CONTENT_STYLE,
  TOOLTIP_CURSOR,
  TOOLTIP_LABEL_STYLE,
  chartColor,
} from './chartTheme';
import { hasKmDriven, type KmDrivenRow } from '@/lib/dashboardCharts';
import { useFormat } from '@/hooks/useFormat';
import { usePrefersReducedMotion } from '@/hooks/usePrefersReducedMotion';

export interface KmDrivenChartProps {
  rows: KmDrivenRow[];
}

/** Km percorsi per mese, sommati tra i veicoli propri. */
export function KmDrivenChart({ rows }: KmDrivenChartProps) {
  const { t } = useTranslation();
  const fmt = useFormat();
  const reducedMotion = usePrefersReducedMotion();

  return (
    <ChartCard
      title={t('dashboard.charts.km_driven')}
      emptyMessage={t('dashboard.charts.empty.km')}
      isEmpty={!hasKmDriven(rows)}
      table={{
        columns: [t('dashboard.charts.table.month'), t('dashboard.charts.series.km')],
        rows: rows.map((row) => [row.label, fmt.km(row.km)]),
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
            formatter={(value) => fmt.km(Number(value))}
          />
          <Bar dataKey="km" name={t('dashboard.charts.series.km')} fill={chartColor(1)} radius={[4, 4, 0, 0]} maxBarSize={32} isAnimationActive={!reducedMotion} />
        </BarChart>
      </ResponsiveContainer>
    </ChartCard>
  );
}
