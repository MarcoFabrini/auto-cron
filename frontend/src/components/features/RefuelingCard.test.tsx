import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import i18n from '@/i18n';
import type { Refueling } from '@/api/types/refueling';
import { RefuelingCard } from './RefuelingCard';

const refueling: Refueling = {
  id: 1,
  vehicleId: 7,
  refueledAt: '2026-05-27',
  km: 12345,
  liters: '40.5',
  pricePerLiter: '1.789',
  totalCost: '1234.50',
  fuelType: 'diesel',
  fullTank: true,
  station: null,
  notes: null,
};

function renderCard() {
  return render(
    <MemoryRouter>
      <RefuelingCard refueling={refueling} />
    </MemoryRouter>,
  );
}

describe('RefuelingCard', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it');
  });
  afterEach(async () => {
    // Prima si smonta l'albero, poi si torna all'italiano: niente aggiornamenti fuori da act().
    cleanup();
    await i18n.changeLanguage('it');
  });

  it('in italiano: virgola decimale, punto sulle migliaia, data in italiano', () => {
    renderCard();

    expect(screen.getByText('40,5 L')).toBeInTheDocument();
    expect(screen.getByText('12.345 km')).toBeInTheDocument();
    expect(screen.getByText('27 mag 2026')).toBeInTheDocument();
    expect(screen.getByText(/^1234,50\s€$/)).toBeInTheDocument();
  });

  it('in inglese: punto decimale, virgola sulle migliaia, data e valuta in formato inglese', async () => {
    await i18n.changeLanguage('en');
    renderCard();

    expect(screen.getByText('40.5 L')).toBeInTheDocument();
    expect(screen.getByText('12,345 km')).toBeInTheDocument();
    expect(screen.getByText('27 May 2026')).toBeInTheDocument();
    expect(screen.getByText('€1,234.50')).toBeInTheDocument();
  });
});
