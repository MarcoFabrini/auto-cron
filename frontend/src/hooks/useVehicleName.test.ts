import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook } from '@testing-library/react';
import { useVehicleName } from './useVehicleName';
import { useVehicles } from '@/hooks/useVehicles';

vi.mock('@/hooks/useVehicles', () => ({ useVehicles: vi.fn() }));

const mockedUseVehicles = vi.mocked(useVehicles);

function stubVehicles(data: unknown) {
  mockedUseVehicles.mockReturnValue({ data } as never);
}

describe('useVehicleName', () => {
  beforeEach(() => {
    mockedUseVehicles.mockReset();
  });

  it('ritorna il nome del veicolo con id corrispondente', () => {
    stubVehicles([
      { id: 1, name: 'Panda' },
      { id: 2, name: 'Punto' },
    ]);
    const { result } = renderHook(() => useVehicleName(2));
    expect(result.current).toBe('Punto');
  });

  it('ritorna undefined se l\'id non esiste', () => {
    stubVehicles([{ id: 1, name: 'Panda' }]);
    const { result } = renderHook(() => useVehicleName(99));
    expect(result.current).toBeUndefined();
  });

  it('ritorna undefined finché i dati non sono caricati', () => {
    stubVehicles(undefined);
    const { result } = renderHook(() => useVehicleName(1));
    expect(result.current).toBeUndefined();
  });
});
