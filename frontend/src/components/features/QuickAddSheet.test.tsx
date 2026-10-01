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

  it.each([
    ['condiviso in sola lettura', 'shared' as const],
    ['di un altro membro (owner/admin dell\'org)', 'organization' as const],
  ])('con un solo veicolo proprio non chiede la scelta anche se vede un veicolo %s', async (_case, ownership) => {
    mockedAuthFetch.mockResolvedValue([vehicle(4, 'Panda'), vehicle(5, 'Ducati', ownership)]);
    const user = userEvent.setup();
    renderSheet();

    // Si va dritti su cosa aggiungere per Panda, l'unico veicolo proprio.
    await user.click(await screen.findByText('Nuovo rifornimento'));

    expect(screen.getByTestId('where')).toHaveTextContent('/refueling/new?vehicleId=4');
    expect(screen.queryByText('Ducati')).not.toBeInTheDocument();
  });

  it('mentre la lista si carica non mostra la scelta del veicolo', () => {
    mockedAuthFetch.mockReturnValue(new Promise(() => {}));
    renderSheet();

    expect(screen.queryByText('Scegli un veicolo')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Nuovo veicolo/ })).not.toBeInTheDocument();
  });

  it('con più veicoli propri la scelta mostra solo quelli', async () => {
    mockedAuthFetch.mockResolvedValue([vehicle(4, 'Panda'), vehicle(5, 'Ducati'), vehicle(6, 'Altrui', 'organization')]);
    renderSheet();

    expect(await screen.findByText('Panda')).toBeInTheDocument();
    expect(screen.getByText('Ducati')).toBeInTheDocument();
    expect(screen.queryByText('Altrui')).not.toBeInTheDocument();
  });
});
