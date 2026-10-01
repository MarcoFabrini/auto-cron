import { describe, it, expect, vi, beforeEach } from 'vitest';
import type { ReactElement } from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { authFetch } from '@/api/client';
import type { Vehicle } from '@/api/types/vehicle';
import { VehicleDetailPage } from './VehicleDetailPage';

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

const vehicle = (id: number, name: string): Vehicle => ({
  id,
  name,
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
  archivedAt: null,
  ownership: 'owned',
  permissions: { canEdit: true, canDelete: true, canShare: false },
});

const charts = {
  from: '2026-10',
  to: '2026-10',
  fuelTypes: ['diesel'],
  months: [
    {
      month: '2026-10',
      spending: { refuelings: '10.00', maintenances: '0.00', expenses: '0.00', total: '10.00' },
      kmDriven: 100,
      consumption: { diesel: 12.5 },
    },
  ],
  spendingByCategory: [{ category: 'fuel', amount: '10.00' }],
  totals: { spending: '10.00', kmDriven: 100 },
};

const stats = {
  consumption: { diesel: 12.5 },
  totals: { cost: '10.00', refuelings: 1, maintenances: 0, expenses: 0 },
  currentKm: 100,
  kmDriven: 100,
  costPerKm: 0.1,
};

function renderPage(ownedVehicles: Vehicle[]) {
  mockedAuthFetch.mockImplementation(async (path: string) => {
    if (path === '/api/vehicles') return ownedVehicles;
    if (path === '/api/vehicles/1') return ownedVehicles[0];
    if (path === '/api/vehicles/1/stats') return stats;
    if (path === '/api/vehicles/1/charts?months=12') return charts;
    return []; // liste delle tab
  });
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <MemoryRouter initialEntries={['/vehicles/1']}>
        <Routes>
          <Route path="/vehicles/:id" element={<VehicleDetailPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('VehicleDetailPage', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
  });

  it('con più veicoli propri mostra i grafici del veicolo prima della card informazioni', async () => {
    renderPage([vehicle(1, 'Golf'), vehicle(2, 'Panda')]);

    const chartsHeading = await screen.findByRole('heading', { name: 'Grafici del veicolo' });
    const infoTitle = screen.getByText('Dati veicolo');
    expect(chartsHeading.compareDocumentPosition(infoTitle) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    expect(await screen.findByText('Spese mensili')).toBeInTheDocument();
  });

  it('con un solo veicolo proprio non mostra i grafici (sono già in dashboard)', async () => {
    renderPage([vehicle(1, 'Golf')]);

    expect(await screen.findByText('Dati veicolo')).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Grafici del veicolo' })).not.toBeInTheDocument();
    expect(mockedAuthFetch).not.toHaveBeenCalledWith('/api/vehicles/1/charts?months=12');
  });

  it('"Aggiungi" apre la scelta di cosa inserire per questo veicolo', async () => {
    renderPage([vehicle(1, 'Golf'), vehicle(2, 'Panda')]);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: 'Aggiungi' }));

    const links = [
      ['Nuova manutenzione', '/maintenance/new?vehicleId=1'],
      ['Nuovo rifornimento', '/refueling/new?vehicleId=1'],
      ['Nuova spesa', '/expenses/new?vehicleId=1'],
      ['Nuovo promemoria', '/reminders/new?vehicleId=1'],
    ];
    for (const [name, href] of links) {
      expect(await screen.findByRole('menuitem', { name })).toHaveAttribute('href', href);
    }
  });

  it.each([
    ['condiviso in sola lettura', { ownership: 'shared' as const, permissions: { canEdit: false, canDelete: false, canShare: false } }],
    ['archiviato', { archivedAt: '2026-09-01T10:00:00+02:00' }],
  ])('veicolo %s: nessun "Aggiungi"', async (_case, overrides) => {
    renderPage([{ ...vehicle(1, 'Golf'), ...overrides }, vehicle(2, 'Panda')]);

    expect(await screen.findByText('Dati veicolo')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Aggiungi' })).not.toBeInTheDocument();
  });
});
