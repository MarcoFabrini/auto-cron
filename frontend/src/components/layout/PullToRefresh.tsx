import { type RefObject } from 'react';
import { useTranslation } from 'react-i18next';
import { ArrowDown, Loader2 } from 'lucide-react';
import { usePrefersReducedMotion } from '@/hooks/usePrefersReducedMotion';
import { usePullToRefresh } from '@/hooks/usePullToRefresh';
import { PULL_THRESHOLD } from '@/lib/pullToRefresh';
import { refreshApp } from '@/lib/pwaUpdate';
import { cn } from '@/lib/utils';

export interface PullToRefreshProps {
  /** Il contenitore che scrolla (`<main>` di AppLayout): deve essere `relative`, l'indicatore è assoluto al suo interno. */
  scrollRef: RefObject<HTMLElement | null>;
}

/**
 * PullToRefresh — indicatore del gesto "trascina per aggiornare" (solo PWA installata, layout mobile:
 * regola in lib/pullToRefresh). Non occupa spazio: è assoluto sopra il contenuto e, a riposo, nascosto
 * e fuori dallo schermo, quindi nessun layout shift. Al rilascio oltre la soglia cerca una nuova
 * versione e ricarica (`refreshApp`). Non è l'unico modo di aggiornare: c'è anche l'avviso "Nuova versione".
 */
export function PullToRefresh({ scrollRef }: PullToRefreshProps) {
  const { t } = useTranslation();
  const reducedMotion = usePrefersReducedMotion();
  const { status, distance } = usePullToRefresh(scrollRef, refreshApp);
  if (status === 'idle') return null;

  const label =
    status === 'refreshing' ? t('pull_to_refresh.refreshing') : status === 'ready' ? t('pull_to_refresh.release') : t('pull_to_refresh.pull');
  // Scorre dal bordo alto fino a `distance`; l'indicatore è alto quanto la soglia
  const offset = distance - PULL_THRESHOLD;

  return (
    <div
      role="status"
      aria-live="polite"
      className="pointer-events-none absolute inset-x-0 top-0 z-10 flex justify-center"
      style={{ height: PULL_THRESHOLD, transform: `translateY(${offset}px)` }}
    >
      <div className="mt-2 flex h-fit items-center gap-2 rounded-full border bg-card px-3 py-2 text-xs font-medium text-muted-foreground shadow-md">
        {status === 'refreshing' ? (
          <Loader2 aria-hidden="true" className="size-4 animate-spin" />
        ) : (
          <ArrowDown
            aria-hidden="true"
            className={cn('size-4', !reducedMotion && 'transition-transform', status === 'ready' && 'rotate-180')}
          />
        )}
        <span>{label}</span>
      </div>
    </div>
  );
}
