import { describe, it, expect, vi, beforeEach } from 'vitest';
import type { ReactElement } from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { authFetch } from '@/api/client';
import type { DashboardCharts as DashboardChartsData } from '@/api/types/dashboardCharts';
import type { Vehicle } from '@/api/types/vehicle';
import { VehicleCharts } from './VehicleCharts';

vi.mock('@/api/client', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/client')>()),
  authFetch: vi.fn(),
}));
const mockedAuthFetch = vi.mocked(authFetch);

// In jsdom ResponsiveContainer misura 0×0 e non disegna nulla: dimensione fissa.
vi.mock('recharts', async (importOriginal) => {
  const { cloneElement } = await import('react');
  return {
    ...(await importOriginal<typeof import('recharts')>()),
    ResponsiveContainer: ({ children }: { children: ReactElement<{ width?: number; height?: number }> }) =>
      cloneElement(children, { width: 400, height: 240 }),
  };
});

const charts: DashboardChartsData = {
  from: '2026-10',
  to: '2026-10',
  fuelTypes: ['diesel'],
  months: [
    {
      month: '2026-10',
      spending: { refuelings: '84.58', maintenances: '0.00', expenses: '0.00', total: '84.58' },
      kmDriven: 900,
      consumption: { diesel: 19.5 },
    },
  ],
  spendingByCategory: [{ category: 'fuel', amount: '84.58' }],
  totals: { spending: '84.58', kmDriven: 900 },
};

const listed = (id: number, ownership: Vehicle['ownership']) => ({ id, ownership });

/** Risponde alla lista veicoli e ai grafici; `list` = null lascia la lista in caricamento. */
function stubApi(list: { id: number; ownership: Vehicle['ownership'] }[] | null) {
  mockedAuthFetch.mockImplementation((path: string) => {
    if (path === '/api/vehicles') return list === null ? new Promise(() => {}) : Promise.resolve(list);
    if (/^\/api\/vehicles\/\d+\/charts\?months=12$/.test(path)) return Promise.resolve(charts);
    return Promise.reject(new Error(`unexpected fetch ${path}`));
  });
}

function renderCharts(vehicle: Pick<Vehicle, 'id' | 'ownership' | 'archivedAt'>) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <VehicleCharts vehicle={vehicle} />
    </QueryClientProvider>,
  );
}

const chartsCalls = () => mockedAuthFetch.mock.calls.filter(([path]) => String(path).includes('/charts'));

describe('VehicleCharts', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
  });

  it('veicolo proprio con altri veicoli propri: mostra i 4 grafici del solo veicolo', async () => {
    stubApi([listed(1, 'owned'), listed(2, 'owned')]);
    renderCharts({ id: 1, ownership: 'owned', archivedAt: null });

    expect(await screen.findByRole('heading', { name: 'Grafici del veicolo' })).toBeInTheDocument();
    expect(await screen.findByRole('table', { name: 'Spese mensili' })).toBeInTheDocument();
    expect(screen.getByRole('table', { name: 'Spese per categoria' })).toBeInTheDocument();
    expect(screen.getByRole('table', { name: 'Km percorsi' })).toBeInTheDocument();
    expect(screen.getByRole('table', { name: 'Consumo medio (km/l)' })).toBeInTheDocument();
    expect(mockedAuthFetch).toHaveBeenCalledWith('/api/vehicles/1/charts?months=12');
  });

  it('unico veicolo proprio attivo: niente sezione e nessuna richiesta dei grafici', async () => {
    stubApi([listed(1, 'owned'), listed(7, 'shared')]);
    renderCharts({ id: 1, ownership: 'owned', archivedAt: null });

    await waitFor(() => expect(mockedAuthFetch).toHaveBeenCalledWith('/api/vehicles'));
    expect(screen.queryByRole('heading', { name: 'Grafici del veicolo' })).not.toBeInTheDocument();
    expect(chartsCalls()).toHaveLength(0);
  });

  it('veicolo condiviso: grafici visibili anche se l\'utente ha un solo veicolo proprio', async () => {
    stubApi([listed(1, 'owned'), listed(7, 'shared')]);
    renderCharts({ id: 7, ownership: 'shared', archivedAt: null });

    expect(await screen.findByRole('table', { name: 'Spese mensili' })).toBeInTheDocument();
    expect(mockedAuthFetch).toHaveBeenCalledWith('/api/vehicles/7/charts?months=12');
  });

  it('veicolo proprio archiviato: grafici visibili anche se non ha altri veicoli', async () => {
    stubApi([]);
    renderCharts({ id: 3, ownership: 'owned', archivedAt: '2026-09-01T10:00:00+02:00' });

    expect(await screen.findByRole('table', { name: 'Spese mensili' })).toBeInTheDocument();
    expect(mockedAuthFetch).toHaveBeenCalledWith('/api/vehicles/3/charts?months=12');
  });

  it('veicolo proprio con la lista ancora in caricamento: niente sezione né richiesta', async () => {
    stubApi(null);
    renderCharts({ id: 1, ownership: 'owned', archivedAt: null });

    await waitFor(() => expect(mockedAuthFetch).toHaveBeenCalledWith('/api/vehicles'));
    expect(screen.queryByRole('heading', { name: 'Grafici del veicolo' })).not.toBeInTheDocument();
    expect(chartsCalls()).toHaveLength(0);
  });
});
