import { useEffect, useRef, useState } from 'react';
import { createCornerTapCounter, readDebugFlag, writeDebugFlag } from '@/lib/viewportDebug';

const noStorage = { getItem: () => null, setItem: () => {}, removeItem: () => {} };

type Reading = Record<string, string>;

function px(value: number | undefined): string {
  return value === undefined ? 'n/d' : `${Math.round(value * 10) / 10}`;
}

function measure(probe: HTMLElement | null): Reading {
  const vv = window.visualViewport;
  const nav = document.querySelector<HTMLElement>('nav[aria-label]:not([class*="hidden"])');
  const navRect = Array.from(document.querySelectorAll<HTMLElement>('nav'))
    .map((el) => el.getBoundingClientRect())
    .find((r) => r.height > 0 && r.width > 0);
  const navStyle = nav ? getComputedStyle(nav).paddingBottom : 'n/d';
  return {
    'innerWidth x innerHeight': `${window.innerWidth} x ${window.innerHeight}`,
    'outerHeight': px(window.outerHeight),
    'screen w x h': `${screen.width} x ${screen.height}`,
    'visualViewport h / top': `${px(vv?.height)} / ${px(vv?.offsetTop)}`,
    'documentElement.clientHeight': px(document.documentElement.clientHeight),
    'inset top (env)': probe ? getComputedStyle(probe).paddingTop : 'n/d',
    'inset bottom (env)': probe ? getComputedStyle(probe).paddingBottom : 'n/d',
    'nav bottom (px)': px(navRect?.bottom),
    'nav padding-bottom': navStyle,
    'navigator.standalone': String((navigator as Navigator & { standalone?: boolean }).standalone),
    'display-mode standalone': String(window.matchMedia?.('(display-mode: standalone)').matches),
    'devicePixelRatio': px(window.devicePixelRatio),
  };
}

/**
 * Pannello diagnostico del viewport (vedi lib/viewportDebug): serve a capire, su un telefono reale,
 * perché la barra inferiore non arriva al bordo. Invisibile e inerte finché non lo si attiva.
 */
export function ViewportDebug() {
  const [enabled, setEnabled] = useState(() => readDebugFlag(window.location.search, window.localStorage ?? noStorage));
  const [reading, setReading] = useState<Reading>({});
  const probeRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const tapCorner = createCornerTapCounter();
    const onPointerDown = (event: PointerEvent) => {
      if (tapCorner(event.clientX, event.clientY)) {
        setEnabled((on) => {
          writeDebugFlag(!on, window.localStorage ?? noStorage);
          return !on;
        });
      }
    };
    document.addEventListener('pointerdown', onPointerDown);
    return () => document.removeEventListener('pointerdown', onPointerDown);
  }, []);

  useEffect(() => {
    if (!enabled) return;
    const update = () => setReading(measure(probeRef.current));
    update();
    window.addEventListener('resize', update);
    window.visualViewport?.addEventListener('resize', update);
    const timer = window.setInterval(update, 1000);
    return () => {
      window.removeEventListener('resize', update);
      window.visualViewport?.removeEventListener('resize', update);
      window.clearInterval(timer);
    };
  }, [enabled]);

  if (!enabled) return null;

  return (
    <>
      <div
        ref={probeRef}
        aria-hidden="true"
        className="pointer-events-none invisible fixed left-0 top-0"
        style={{ paddingTop: 'env(safe-area-inset-top)', paddingBottom: 'env(safe-area-inset-bottom)' }}
      />
      <pre className="pointer-events-none fixed inset-x-2 top-24 z-[200] whitespace-pre-wrap rounded bg-black/85 p-2 font-mono text-[11px] leading-snug text-white">
        {Object.entries(reading)
          .map(([key, value]) => `${key}: ${value}`)
          .join('\n')}
      </pre>
    </>
  );
}
