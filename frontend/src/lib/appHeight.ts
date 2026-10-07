/**
 * In una PWA iOS installata (visto su iOS 27.0.1: innerHeight 812 con screen.height 874) il viewport è più
 * corto dello schermo della sola altezza dell'inset superiore, ma la pagina viene disegnata dall'alto: in
 * basso resta una fascia vuota e la barra fissa non arriva al bordo. Qui si calcola l'altezza dello schermo
 * da usare al posto di quella del viewport; fuori da quel caso la variabile non viene impostata.
 */
export const APP_HEIGHT_VAR = '--app-height';

export interface ViewportMetrics {
  standalone: boolean;
  innerWidth: number;
  innerHeight: number;
  screenWidth: number;
  screenHeight: number;
}

/** Altezza da forzare, o null se il viewport è già a posto o non è il caso iOS standalone in verticale. */
export function appHeightFor(m: ViewportMetrics): number | null {
  if (!m.standalone) return null;
  if (m.innerWidth !== m.screenWidth) return null; // orizzontale: screen.* non segue la rotazione
  const missing = m.screenHeight - m.innerHeight;
  if (missing <= 0 || missing > 100) return null; // allineato, o scarto troppo grande per essere un inset
  return m.screenHeight;
}

export function syncAppHeight(win: Window = window): void {
  const standalone =
    (win.navigator as Navigator & { standalone?: boolean }).standalone === true ||
    (win.matchMedia?.('(display-mode: standalone)').matches ?? false);
  const height = appHeightFor({
    standalone,
    innerWidth: win.innerWidth,
    innerHeight: win.innerHeight,
    screenWidth: win.screen.width,
    screenHeight: win.screen.height,
  });
  const style = win.document.documentElement.style;
  if (height === null) style.removeProperty(APP_HEIGHT_VAR);
  else style.setProperty(APP_HEIGHT_VAR, `${height}px`);
}

export function installAppHeightSync(win: Window = window): void {
  syncAppHeight(win);
  win.addEventListener('resize', () => syncAppHeight(win));
  win.addEventListener('orientationchange', () => syncAppHeight(win));
  win.addEventListener('pageshow', () => syncAppHeight(win));
}
