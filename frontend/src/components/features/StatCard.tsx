import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui';

/**
 * StatCard feature — singolo riquadro statistica con icona + label + valore.
 *
 * Usato in dashboard, sezioni summary, ecc.
 *
 * @example
 * <StatCard Icon={Car} label="Veicoli" value={3} />
 * <StatCard Icon={Fuel} label="Spesa mese" value="€123,40" />
 */
export interface StatCardProps {
  Icon: LucideIcon;
  label: string;
  /** Valore mostrato large (numero, currency, "—" se vuoto) */
  value: string | number;
}

export function StatCard({ Icon, label, value }: StatCardProps) {
  return (
    <Card>
      <CardContent standalone className="flex items-center gap-3">
        <div className="rounded-md bg-primary/10 p-3">
          <Icon className="size-5 text-primary" />
        </div>
        <div className="min-w-0 flex-1">
          <div className="text-xs font-medium text-muted-foreground">{label}</div>
          <div className="truncate text-xl font-bold">{value}</div>
        </div>
      </CardContent>
    </Card>
  );
}
