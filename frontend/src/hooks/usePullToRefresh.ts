import { useEffect, useRef, useState, useSyncExternalStore, type RefObject } from 'react';
import { classifyGesture, isPastThreshold, isPullToRefreshEnabled, pullDistance, shouldIgnoreTarget } from '@/lib/pullToRefresh';

export type PullStatus = 'idle' | 'pulling' | 'ready' | 'refreshing';

export interface PullState {
  status: PullStatus;
  /** Distanza dell'indicatore in px (già con la resistenza). */
  distance: number;
}

const IDLE: PullState = { status: 'idle', distance: 0 };
const QUERIES = ['(display-mode: standalone)', '(max-width: 767px)'];

function subscribeEnvironment(onChange: () => void): () => void {
  if (typeof window.matchMedia !== 'function') return () => {};
  const lists = QUERIES.map((query) => window.matchMedia(query));
  lists.forEach((mq) => mq.addEventListener('change', onChange));
  return () => lists.forEach((mq) => mq.removeEventListener('change', onChange));
}

/**
 * Regola di attivazione (lib/pullToRefresh.isPullToRefreshEnabled) letta come valore esterno: si aggiorna
 * se l'app passa da mobile a desktop (rotazione, finestra ridimensionata) senza ricaricare.
 */
export function usePullToRefreshEnabled(): boolean {
  return useSyncExternalStore(subscribeEnvironment, () => isPullToRefreshEnabled(), () => false);
}

/**
 * usePullToRefresh — gesto "trascina verso il basso" sul contenitore che scrolla davvero (`<main>`).
 * Parte solo con il contenitore a scrollTop 0 e un trascinamento chiaramente verticale; lascia stare
 * campi di testo, grafici, overlay aperti. Al rilascio oltre la soglia chiama `onRefresh`.
 *
 * Gli handler sono listener nativi: `touchmove` deve essere non passivo per poter fare `preventDefault`
 * ed evitare che iOS faccia scorrere/rimbalzare la pagina mentre si tira.
 */
export function usePullToRefresh(
  containerRef: RefObject<HTMLElement | null>,
  onRefresh: () => Promise<void>,
): PullState {
  const enabled = usePullToRefreshEnabled();
  const [state, setState] = useState<PullState>(IDLE);
  // L'ultima callback senza rimontare i listener a ogni render
  const onRefreshRef = useRef(onRefresh);
  useEffect(() => {
    onRefreshRef.current = onRefresh;
  });

  useEffect(() => {
    const el = containerRef.current;
    if (!enabled || !el) return;

    let startX = 0;
    let startY = 0;
    let tracking = false;
    let decision: ReturnType<typeof classifyGesture> = 'pending';
    let distance = 0;
    let busy = false;

    const reset = () => {
      tracking = false;
      decision = 'pending';
      distance = 0;
      if (!busy) setState(IDLE);
    };

    const onTouchStart = (event: TouchEvent) => {
      const touch = event.touches[0];
      if (busy || event.touches.length !== 1 || !touch) return;
      if (el.scrollTop > 0 || shouldIgnoreTarget(event.target)) return;
      tracking = true;
      decision = 'pending';
      startX = touch.clientX;
      startY = touch.clientY;
    };

    const onTouchMove = (event: TouchEvent) => {
      const touch = event.touches[0];
      if (!tracking || busy || !touch) return;
      if (decision === 'pending') decision = classifyGesture(touch.clientX - startX, touch.clientY - startY, el.scrollTop);
      if (decision === 'ignore') {
        // Non è un pull (scroll, swipe orizzontale): tracking spento fino al prossimo touchstart
        reset();
        return;
      }
      if (decision !== 'pull') return;
      // Il gesto è nostro: niente scroll né rimbalzo della pagina sotto il dito
      if (event.cancelable) event.preventDefault();
      distance = pullDistance(touch.clientY - startY);
      setState({ status: isPastThreshold(distance) ? 'ready' : 'pulling', distance });
    };

    const onTouchEnd = () => {
      if (!tracking) return;
      const release = decision === 'pull' && isPastThreshold(distance);
      if (!release) {
        reset();
        return;
      }
      tracking = false;
      busy = true;
      setState({ status: 'refreshing', distance });
      onRefreshRef
        .current()
        .catch(() => undefined)
        .finally(() => {
          busy = false;
          reset();
        });
    };

    el.addEventListener('touchstart', onTouchStart, { passive: true });
    el.addEventListener('touchmove', onTouchMove, { passive: false });
    el.addEventListener('touchend', onTouchEnd);
    el.addEventListener('touchcancel', reset);
    return () => {
      el.removeEventListener('touchstart', onTouchStart);
      el.removeEventListener('touchmove', onTouchMove);
      el.removeEventListener('touchend', onTouchEnd);
      el.removeEventListener('touchcancel', reset);
    };
  }, [enabled, containerRef]);

  return enabled ? state : IDLE;
}
