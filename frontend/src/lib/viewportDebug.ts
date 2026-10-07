/**
 * Diagnostica del viewport per la PWA su iPhone: il pannello si attiva con `?debug=1` nell'URL oppure con
 * 5 tocchi ravvicinati nell'angolo in alto a sinistra (in standalone l'URL non si può scrivere). Lo stato
 * resta in localStorage finché non si ripete il gesto o si apre `?debug=0`.
 */
const STORAGE_KEY = 'autocron.debug';
export const TAP_COUNT = 5;
export const TAP_WINDOW_MS = 2000;
export const CORNER_PX = 80;

export function readDebugFlag(search: string, storage: Pick<Storage, 'getItem' | 'setItem' | 'removeItem'>): boolean {
  const param = new URLSearchParams(search).get('debug');
  try {
    if (param === '1') {
      storage.setItem(STORAGE_KEY, '1');
      return true;
    }
    if (param === '0') {
      storage.removeItem(STORAGE_KEY);
      return false;
    }
    return storage.getItem(STORAGE_KEY) === '1';
  } catch {
    return param === '1';
  }
}

export function writeDebugFlag(on: boolean, storage: Pick<Storage, 'setItem' | 'removeItem'>): void {
  try {
    if (on) storage.setItem(STORAGE_KEY, '1');
    else storage.removeItem(STORAGE_KEY);
  } catch {
    // storage non disponibile: il pannello vale solo per la sessione
  }
}

/** Tiene i tocchi nell'angolo; torna true quando ne arrivano TAP_COUNT dentro la finestra. */
export function createCornerTapCounter(now: () => number = Date.now) {
  let taps: number[] = [];
  return (x: number, y: number): boolean => {
    if (x > CORNER_PX || y > CORNER_PX) {
      taps = [];
      return false;
    }
    const t = now();
    taps = [...taps.filter((at) => t - at <= TAP_WINDOW_MS), t];
    if (taps.length >= TAP_COUNT) {
      taps = [];
      return true;
    }
    return false;
  };
}
