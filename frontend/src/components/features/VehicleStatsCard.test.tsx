import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import i18n from '@/i18n';
import { VehicleStatsCard } from './VehicleStatsCard';
import type { VehicleStats } from '@/api/types/vehicleStats';

const STATS: VehicleStats = {
  consumption: { diesel: 17.46, lpg: null },
  totals: { cost: '1234.50', refuelings: 12, maintenances: 3, expenses: 5 },
  currentKm: 60_000,
  kmDriven: 12_345,
  costPerKm: 0.1,
};

describe('VehicleStatsCard', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it');
  });

  it('mostra km percorsi, costo al km, costo totale e conteggi', () => {
    render(<VehicleStatsCard stats={STATS} />);

    expect(screen.getByText('12.345 km')).toBeInTheDocument();
    expect(screen.getByText(/0,10/)).toBeInTheDocument();
    expect(screen.getByText(/1234,50/)).toBeInTheDocument();
    expect(screen.getByText('12')).toBeInTheDocument();
  });

  it('consumo per carburante: valore in km/l, oppure spiegazione se non calcolabile', () => {
    render(<VehicleStatsCard stats={STATS} />);

    expect(screen.getByText('17,5 km/l')).toBeInTheDocument();
    expect(screen.getByText('Servono almeno 2 pieni')).toBeInTheDocument();
  });

  it('costo al km non calcolabile: trattino, niente NaN', () => {
    render(<VehicleStatsCard stats={{ ...STATS, costPerKm: null, kmDriven: 0 }} />);

    expect(screen.getByText('—')).toBeInTheDocument();
    expect(screen.queryByText(/NaN/)).not.toBeInTheDocument();
  });
});
