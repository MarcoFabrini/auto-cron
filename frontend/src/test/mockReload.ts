import { vi } from 'vitest';

/** Sostituisce `window.location.reload` con un mock (jsdom non sa ricaricare); si ripristina con `vi.restoreAllMocks`. */
export function mockLocationReload() {
  const reload = vi.fn();
  vi.spyOn(window, 'location', 'get').mockReturnValue({ ...window.location, reload });
  return reload;
}
