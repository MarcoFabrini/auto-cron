import type { ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Text } from '@/components/ui';

export interface ChartCardProps {
  title: string;
  /** Messaggio al posto del grafico quando la serie non ha dati nella finestra. */
  emptyMessage: string;
  isEmpty: boolean;
  /** Il grafico (dentro un `ResponsiveContainer`): riceve un'altezza fissa, mobile-first. */
  children: ReactNode;
  /** Contenuto sotto il grafico (es. legenda con importi). */
  footer?: ReactNode;
}

/** Card comune ai grafici della dashboard: titolo, grafico ad altezza fissa o messaggio vuoto. */
export function ChartCard({ title, emptyMessage, isEmpty, children, footer }: ChartCardProps) {
  return (
    <Card className="min-w-0">
      <CardHeader>
        <CardTitle>{title}</CardTitle>
      </CardHeader>
      <CardContent>
        {isEmpty ? (
          <Text variant="muted" className="flex h-24 items-center text-sm">
            {emptyMessage}
          </Text>
        ) : (
          <>
            <div className="h-56 w-full md:h-64">{children}</div>
            {footer}
          </>
        )}
      </CardContent>
    </Card>
  );
}
