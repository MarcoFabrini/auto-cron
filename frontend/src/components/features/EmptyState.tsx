import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Card, CardContent, Heading, Text } from '@/components/ui';

/**
 * EmptyState feature — placeholder centrato per liste vuote.
 *
 * @example
 * <EmptyState
 *   Icon={Car}
 *   title="Nessun veicolo"
 *   description="Aggiungi il tuo primo veicolo per iniziare."
 *   action={<Button asChild><Link to="/vehicles/new">Aggiungi</Link></Button>}
 * />
 */
export interface EmptyStateProps {
  Icon?: LucideIcon;
  title: string;
  description?: string;
  action?: ReactNode;
}

export function EmptyState({ Icon, title, description, action }: EmptyStateProps) {
  return (
    <Card>
      <CardContent className="flex flex-col items-center justify-center gap-3 px-4 py-12 text-center md:px-6 md:py-12">
        {Icon && (
          <div className="rounded-full bg-muted p-3">
            <Icon className="size-6 text-muted-foreground" />
          </div>
        )}
        <Heading level={3}>{title}</Heading>
        {description && <Text variant="muted">{description}</Text>}
        {action && <div className="mt-2">{action}</div>}
      </CardContent>
    </Card>
  );
}
