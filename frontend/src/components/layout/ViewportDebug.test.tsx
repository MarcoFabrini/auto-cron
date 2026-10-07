import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import { ViewportDebug } from './ViewportDebug';

describe('ViewportDebug', () => {
  beforeEach(() => {
    const data = new Map<string, string>();
    vi.stubGlobal('localStorage', {
      getItem: (k: string) => data.get(k) ?? null,
      setItem: (k: string, v: string) => void data.set(k, v),
      removeItem: (k: string) => void data.delete(k),
    });
    vi.stubGlobal('matchMedia', () => ({ matches: false }));
  });
  afterEach(() => {
    vi.unstubAllGlobals();
    window.history.replaceState(null, '', '/');
  });

  it('non mostra nulla di default', () => {
    render(<ViewportDebug />);
    expect(screen.queryByText(/innerWidth/)).not.toBeInTheDocument();
  });

  it('con ?debug=1 mostra le misure', () => {
    window.history.replaceState(null, '', '/?debug=1');
    render(<ViewportDebug />);
    expect(screen.getByText(/innerWidth x innerHeight/)).toBeInTheDocument();
    expect(screen.getByText(/inset bottom \(env\)/)).toBeInTheDocument();
  });

  it('cinque tocchi nell\'angolo lo accendono', () => {
    render(<ViewportDebug />);
    for (let i = 0; i < 5; i += 1) {
      fireEvent.pointerDown(document, { clientX: 10, clientY: 10 });
    }
    expect(screen.getByText(/innerWidth x innerHeight/)).toBeInTheDocument();
  });
});
