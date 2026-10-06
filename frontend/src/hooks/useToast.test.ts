import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { act, renderHook } from '@testing-library/react';
import { useToast } from './useToast';

/** Lo store dei toast è a livello di modulo: ogni test lo svuota attraverso l'API pubblica. */
function setup() {
  return renderHook(() => useToast());
}

describe('useToast', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    // Svuota lo store di modulo: chiude tutto e lascia scattare i timer di rimozione prima di tornare ai timer veri
    const { result, unmount } = setup();
    act(() => result.current.dismiss());
    act(() => {
      vi.runAllTimers();
    });
    unmount();
    vi.useRealTimers();
  });

  it('aggiunge un toast aperto, con la variante richiesta', () => {
    const { result } = setup();

    act(() => {
      result.current.toast({ title: 'Salvato', variant: 'success' });
    });

    expect(result.current.toasts).toHaveLength(1);
    expect(result.current.toasts[0]).toMatchObject({ title: 'Salvato', variant: 'success', open: true });
  });

  it('tiene al massimo 3 toast, i più recenti per primi', () => {
    const { result } = setup();

    act(() => {
      for (const title of ['uno', 'due', 'tre', 'quattro']) result.current.toast({ title });
    });

    expect(result.current.toasts.map((t) => t.title)).toEqual(['quattro', 'tre', 'due']);
  });

  it('dismiss chiude il toast subito e lo rimuove dopo il ritardo di uscita (5 s)', () => {
    const { result } = setup();
    act(() => {
      result.current.toast({ title: 'Errore', variant: 'error' });
    });
    const id = result.current.toasts[0]!.id;

    act(() => result.current.dismiss(id));
    expect(result.current.toasts[0]).toMatchObject({ id, open: false });

    act(() => {
      vi.advanceTimersByTime(4_999);
    });
    expect(result.current.toasts).toHaveLength(1);
    act(() => {
      vi.advanceTimersByTime(1);
    });
    expect(result.current.toasts).toHaveLength(0);
  });

  it('dismiss senza id chiude tutti i toast aperti', () => {
    const { result } = setup();
    act(() => {
      result.current.toast({ title: 'a' });
      result.current.toast({ title: 'b' });
    });

    act(() => result.current.dismiss());

    expect(result.current.toasts.map((t) => t.open)).toEqual([false, false]);
  });

  it('il controllo restituito da toast() aggiorna e chiude solo quel toast', () => {
    const { result } = setup();
    let handle!: ReturnType<typeof result.current.toast>;
    act(() => {
      handle = result.current.toast({ title: 'In corso…' });
      result.current.toast({ title: 'Altro' });
    });

    act(() => handle.update({ title: 'Fatto', variant: 'success' }));
    expect(result.current.toasts.find((t) => t.id === handle.id)).toMatchObject({ title: 'Fatto', variant: 'success' });
    expect(result.current.toasts.find((t) => t.title === 'Altro')).toMatchObject({ open: true });

    act(() => handle.dismiss());
    expect(result.current.toasts.find((t) => t.id === handle.id)?.open).toBe(false);
    expect(result.current.toasts.find((t) => t.title === 'Altro')?.open).toBe(true);
  });

  it("onOpenChange(false) (chiusura dalla UI) equivale a dismiss, onOpenChange(true) non fa nulla", () => {
    const { result } = setup();
    act(() => {
      result.current.toast({ title: 'chiudimi' });
    });
    const toast = result.current.toasts[0]!;

    act(() => toast.onOpenChange?.(true));
    expect(result.current.toasts[0]?.open).toBe(true);

    act(() => toast.onOpenChange?.(false));
    expect(result.current.toasts[0]?.open).toBe(false);
  });

  it('più componenti vedono lo stesso elenco; smontato un componente non riceve più aggiornamenti', () => {
    const a = setup();
    const b = setup();

    act(() => {
      a.result.current.toast({ title: 'condiviso' });
    });
    expect(b.result.current.toasts.map((t) => t.title)).toEqual(['condiviso']);

    b.unmount();
    act(() => {
      a.result.current.toast({ title: 'dopo lo smontaggio' });
    });
    expect(a.result.current.toasts).toHaveLength(2);
    expect(b.result.current.toasts).toHaveLength(1);
  });
});
