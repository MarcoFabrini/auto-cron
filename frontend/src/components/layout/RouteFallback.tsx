import { Spinner } from '@/components/ui';
import { cn } from '@/lib/utils';

export interface RouteFallbackProps {
  /** Pagina intera (rotte pubbliche); altrimenti un'area centrata dentro la shell. */
  fullScreen?: boolean;
}

/** Fallback di Suspense mentre il chunk di una pagina si scarica: spinner con l'etichetta tradotta. */
export function RouteFallback({ fullScreen = false }: RouteFallbackProps) {
  return (
    <div className={cn('flex items-center justify-center', fullScreen ? 'min-h-dvh bg-background' : 'min-h-[50vh]')}>
      <Spinner size="lg" />
    </div>
  );
}
