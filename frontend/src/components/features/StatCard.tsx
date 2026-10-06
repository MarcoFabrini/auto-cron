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
      {/* Sotto sm la card è in colonna: in una griglia a 2 colonne su telefono, icona + gap + padding
          lasciavano ~70px al valore, che si troncava ("€6,827...."). Il valore è un filo più piccolo e stretto
          per far stare anche "€123,456.78" a 360px. Da sm in su resta icona a fianco, come prima. */}
      <CardContent standalone className="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-3">
        <div className="rounded-md bg-primary/10 p-3">
          <Icon className="size-5 text-primary" />
        </div>
        <div className="w-full min-w-0 sm:w-auto sm:flex-1">
          <div className="text-xs font-medium text-muted-foreground">{label}</div>
          <div className="truncate text-lg font-bold tracking-tight sm:text-xl sm:tracking-normal">{value}</div>
        </div>
      </CardContent>
    </Card>
  );
}
