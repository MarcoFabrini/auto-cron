import { describe, it, expect } from 'vitest';
import { shouldShowVehicleCharts } from './vehicleCharts';

const owned = (id: number) => ({ id, ownership: 'owned' as const });

describe('shouldShowVehicleCharts', () => {
  it('nasconde i grafici del veicolo proprio se è l\'unico proprio attivo', () => {
    expect(shouldShowVehicleCharts({ ...owned(1), archivedAt: null }, [owned(1)])).toBe(false);
  });

  it('li mostra se l\'utente ha un altro veicolo proprio', () => {
    expect(shouldShowVehicleCharts({ ...owned(1), archivedAt: null }, [owned(1), owned(2)])).toBe(true);
  });

  it('li mostra sempre su un veicolo condiviso o di un altro membro', () => {
    expect(shouldShowVehicleCharts({ id: 3, ownership: 'shared', archivedAt: null }, [owned(1)])).toBe(true);
    expect(shouldShowVehicleCharts({ id: 4, ownership: 'organization', archivedAt: null }, [owned(1)])).toBe(true);
  });

  it('li mostra su un veicolo proprio archiviato, anche se è l\'unico', () => {
    expect(shouldShowVehicleCharts({ ...owned(1), archivedAt: '2026-09-01T10:00:00+02:00' }, [])).toBe(true);
  });

  it('con una lista che non contiene il veicolo e nessun altro proprio li nasconde', () => {
    expect(shouldShowVehicleCharts({ ...owned(1), archivedAt: null }, [{ id: 9, ownership: 'shared' }])).toBe(false);
  });
});
