import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { act, cleanup, renderHook } from '@testing-library/react';
import i18n from '@/i18n';
import { useFormat } from './useFormat';

describe('useFormat', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it');
  });
  afterEach(async () => {
    // Prima si smonta l'albero, poi si torna all'italiano: niente aggiornamenti fuori da act().
    cleanup();
    await i18n.changeLanguage('it');
  });

  it('in italiano formatta con it-IT', () => {
    const { result } = renderHook(() => useFormat());
    const fmt = result.current;

    expect(fmt.locale).toBe('it-IT');
    expect(fmt.km(12345)).toBe('12.345 km');
    expect(fmt.decimal(12345.6)).toBe('12.345,6');
    expect(fmt.currency('1234.5')).toMatch(/^1234,50\s€$/);
    expect(fmt.date('2026-05-27')).toBe('27 mag 2026');
    expect(fmt.month('2026-05')).toBe('mag 26');
    expect(fmt.bytes(1536)).toBe('1,5 KB');
  });

  it('cambiando lingua i valori si aggiornano senza rimontare', async () => {
    const { result } = renderHook(() => useFormat());
    expect(result.current.decimal(12345.6)).toBe('12.345,6');

    await act(() => i18n.changeLanguage('en'));

    const fmt = result.current;
    expect(fmt.locale).toBe('en-GB');
    expect(fmt.km(12345)).toBe('12,345 km');
    expect(fmt.decimal(12345.6)).toBe('12,345.6');
    expect(fmt.currency('1234.5')).toBe('€1,234.50');
    expect(fmt.date('2026-05-27')).toBe('27 May 2026');
    expect(fmt.month('2026-05')).toBe('May 26');
    expect(fmt.bytes(1536)).toBe('1.5 KB');
  });

  it('l\'oggetto resta lo stesso finché la lingua non cambia', async () => {
    const { result, rerender } = renderHook(() => useFormat());
    const first = result.current;

    rerender();
    expect(result.current).toBe(first);

    await act(() => i18n.changeLanguage('en'));
    expect(result.current).not.toBe(first);
  });

  it('decimal rispetta i decimali massimi e il valore assente', () => {
    const { result } = renderHook(() => useFormat());

    expect(result.current.decimal(8.456, 2)).toBe('8,46');
    expect(result.current.decimal(null)).toBe('—');
  });
});
