import { describe, it, expect, afterEach, vi } from 'vitest';
import { act, renderHook } from '@testing-library/react';
import { usePrefersReducedMotion } from './usePrefersReducedMotion';

/** matchMedia finto con `change` pilotabile dal test. */
function stubMatchMedia(initial: boolean) {
  let matches = initial;
  const listeners = new Set<() => void>();
  vi.stubGlobal(
    'matchMedia',
    vi.fn(() => ({
      get matches() {
        return matches;
      },
      addEventListener: (_: string, cb: () => void) => listeners.add(cb),
      removeEventListener: (_: string, cb: () => void) => listeners.delete(cb),
    })),
  );
  return {
    set(next: boolean) {
      matches = next;
      listeners.forEach((cb) => cb());
    },
    listeners,
  };
}

describe('usePrefersReducedMotion', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('senza matchMedia (jsdom) è false', () => {
    const { result } = renderHook(() => usePrefersReducedMotion());
    expect(result.current).toBe(false);
  });

  it('legge la preferenza e la segue quando cambia', () => {
    const mq = stubMatchMedia(false);
    const { result } = renderHook(() => usePrefersReducedMotion());
    expect(result.current).toBe(false);

    act(() => mq.set(true));
    expect(result.current).toBe(true);
  });

  it('parte da true se la preferenza è già attiva e si disiscrive allo smontaggio', () => {
    const mq = stubMatchMedia(true);
    const { result, unmount } = renderHook(() => usePrefersReducedMotion());
    expect(result.current).toBe(true);
    expect(mq.listeners.size).toBe(1);

    unmount();
    expect(mq.listeners.size).toBe(0);
  });
});
