import { describe, it, expect, vi, beforeEach } from 'vitest';
import type { ReactElement } from 'react';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { authFetch } from '@/api/client';
import type { Vehicle } from '@/api/types/vehicle';
import { DashboardPage } from './DashboardPage';

vi.mock('@/api/client', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/client')>()),
  authFetch: vi.fn(),
}));
const mockedAuthFetch = vi.mocked(authFetch);

vi.mock('recharts', async (importOriginal) => {
  const { cloneElement } = await import('react');
  return {
    ...(await importOriginal<typeof import('recharts')>()),
    ResponsiveContainer: ({ children }: { children: ReactElement<{ width?: number; height?: number }> }) =>
      cloneElement(children, { width: 400, height: 240 }),
  };
});

const CHARTS_URL = '/api/dashboard/charts?months=12';

const vehicle = (id: number, ownership: Vehicle['ownership']): Vehicle => ({
  id,
  name: `Auto ${id}`,
  brand: 'Fiat',
  model: 'Panda',
  year: 2020,
  licensePlate: null,
  vin: null,
  type: 'car',
  fuelType: 'diesel',
  secondaryFuelType: null,
  initialKm: 0,
  notes: null,
  ownership,
  permissions: { canEdit: ownership !== 'shared', canDelete: ownership !== 'shared', canShare: ownership !== 'shared' },
});

const zero = { refuelings: '0.00', maintenances: '0.00', expenses: '0.00', total: '0.00' };

function mockApi(vehicles: Vehicle[]) {
  mockedAuthFetch.mockImplementation(async (path: string) => {
    if (path === '/api/vehicles') return vehicles;
    if (/^\/api\/vehicles\/\d+\/stats$/.test(path)) {
      return { consumption: {}, totals: { cost: '0.00', refuelings: 0, maintenances: 0, expenses: 0 }, currentKm: 0, kmDriven: 0, costPerKm: null };
    }
    if (path.startsWith('/api/reminders/upcoming')) return [];
    if (path === CHARTS_URL) {
      return {
        from: '2026-10',
        to: '2026-10',
        fuelTypes: ['diesel'],
        months: [{ month: '2026-10', spending: zero, kmDriven: 0, consumption: { diesel: null } }],
        spendingByCategory: [],
        totals: { spending: '0.00', kmDriven: 0 },
      };
    }
    throw new Error(`unexpected fetch ${path}`);
  });
}

function renderPage() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <MemoryRouter>
        <DashboardPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('DashboardPage — grafici', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
  });

  it('solo veicoli condivisi o di altri membri: niente sezione grafici e nessuna richiesta', async () => {
    mockApi([vehicle(1, 'shared'), vehicle(2, 'organization')]);
    renderPage();

    // Lista caricata: l'avviso sui veicoli esclusi cita anche i grafici.
    expect(
      await screen.findByText('Totali, grafici e scadenze riguardano solo i tuoi veicoli: 2 veicoli che vedi ma non sono tuoi sono esclusi.'),
    ).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Andamento' })).not.toBeInTheDocument();
    expect(mockedAuthFetch).not.toHaveBeenCalledWith(CHARTS_URL);
  });

  it('con almeno un veicolo proprio la sezione grafici compare', async () => {
    mockApi([vehicle(1, 'owned'), vehicle(2, 'shared')]);
    renderPage();

    expect(await screen.findByRole('heading', { name: 'Andamento' })).toBeInTheDocument();
    expect(await screen.findByText('Spese mensili')).toBeInTheDocument();
    expect(mockedAuthFetch).toHaveBeenCalledWith(CHARTS_URL);
  });
});
