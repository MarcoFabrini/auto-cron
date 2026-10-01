import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { authFetch } from '@/api/client';
import { QuickAddSheet } from './QuickAddSheet';
import type { Vehicle } from '@/api/types/vehicle';

vi.mock('@/api/client', () => ({ authFetch: vi.fn() }));
const mockedAuthFetch = vi.mocked(authFetch);

const vehicle = (id: number, name: string, ownership: Vehicle['ownership'] = 'owned'): Vehicle => ({
  id,
  name,
  brand: 'Fiat',
  model: 'Panda',
  year: 2020,
  licensePlate: null,
  vin: null,
  type: 'car',
  fuelType: 'gasoline',
  secondaryFuelType: null,
  initialKm: 0,
  notes: null,
  ownership,
  permissions: {
    canEdit: ownership !== 'shared',
    canDelete: ownership !== 'shared',
    canShare: ownership !== 'shared',
  },
});

function Where() {
  const l = useLocation();
  return <div data-testid="where">{l.pathname + l.search}</div>;
}

function renderSheet() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <MemoryRouter>
        <QuickAddSheet open onOpenChange={() => {}} />
        <Where />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('QuickAddSheet', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
  });

  it('con un solo veicolo salta la scelta del veicolo e propone subito cosa aggiungere', async () => {
    mockedAuthFetch.mockResolvedValue([vehicle(4, 'Panda')]);
    const user = userEvent.setup();
    renderSheet();

    await user.click(await screen.findByText('Nuovo rifornimento'));

    expect(screen.getByTestId('where')).toHaveTextContent('/refueling/new?vehicleId=4');
  });

  it('con più veicoli chiede prima quale', async () => {
    mockedAuthFetch.mockResolvedValue([vehicle(4, 'Panda'), vehicle(5, 'Ducati')]);
    renderSheet();

    expect(await screen.findByText('Panda')).toBeInTheDocument();
    expect(screen.getByText('Ducati')).toBeInTheDocument();
    expect(screen.queryByText('Nuovo rifornimento')).not.toBeInTheDocument();
  });

  it('con un solo veicolo "indietro" riapre la scelta (per aggiungerne un altro)', async () => {
    mockedAuthFetch.mockResolvedValue([vehicle(4, 'Panda')]);
    const user = userEvent.setup();
    renderSheet();

    await user.click(await screen.findByRole('button', { name: 'Indietro' }));

    expect(await screen.findByRole('button', { name: /Nuovo veicolo/ })).toBeInTheDocument();
  });

  it('non propone i veicoli condivisi in sola lettura', async () => {
    mockedAuthFetch.mockResolvedValue([vehicle(4, 'Panda'), vehicle(5, 'Ducati', 'shared')]);
    const user = userEvent.setup();
    renderSheet();

    // Resta un solo veicolo modificabile: si salta la scelta e si va dritti su Panda.
    await user.click(await screen.findByText('Nuovo rifornimento'));

    expect(screen.getByTestId('where')).toHaveTextContent('/refueling/new?vehicleId=4');
    expect(screen.queryByText('Ducati')).not.toBeInTheDocument();
  });
});
