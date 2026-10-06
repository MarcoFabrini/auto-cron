import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { ApiError, authFetch } from '@/api/client';
import type { Vehicle } from '@/api/types/vehicle';
import type { RefuelingResponse } from '@/api/types/refueling';
import { RefuelingEditPage } from './RefuelingEditPage';

vi.mock('@/api/client', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/client')>()),
  authFetch: vi.fn(),
}));
const mockedAuthFetch = vi.mocked(authFetch);

const refueling: RefuelingResponse = {
  id: 5,
  vehicle: { id: 7, name: 'Golf', brand: 'VW', model: 'Golf' },
  refueledAt: '2026-09-10T00:00:00+00:00',
  km: 45000,
  liters: '40.00',
  pricePerLiter: '1.800',
  totalCost: '72.00',
  fuelType: 'diesel',
  fullTank: true,
  station: null,
  notes: null,
};

const vehicle = (overrides: Partial<Vehicle> = {}): Vehicle => ({
  id: 7,
  name: 'Golf',
  brand: 'VW',
  model: 'Golf',
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
  ...overrides,
});

function mockApi(vehicleResult: () => Promise<Vehicle>) {
  mockedAuthFetch.mockImplementation(async (path: string) => {
    if (path === '/api/refuelings/5') return refueling;
    if (path === '/api/vehicles/7') return vehicleResult();
    throw new Error(`unexpected fetch ${path}`);
  });
}

function renderPage() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <MemoryRouter initialEntries={['/refueling/5/edit']}>
        <Routes>
          <Route path="/refueling/:id/edit" element={<RefuelingEditPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('RefuelingEditPage — permessi sul veicolo', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
  });

  it('finché il veicolo carica il form non compare (nemmeno per un istante)', async () => {
    mockApi(() => new Promise(() => {}));
    renderPage();

    expect(await screen.findByRole('heading', { level: 1, name: 'Modifica rifornimento' })).toBeInTheDocument();
    expect(screen.queryByLabelText(/Litri/)).not.toBeInTheDocument();
  });

  it('se il veicolo risponde 403 mostra l\'errore e nessun form', async () => {
    mockApi(() => Promise.reject(new ApiError('http.403', 403)));
    renderPage();

    expect(await screen.findByRole('alert')).toBeInTheDocument();
    expect(screen.queryByLabelText(/Litri/)).not.toBeInTheDocument();
  });

  it('sola lettura: avviso e nessun form', async () => {
    mockApi(async () =>
      vehicle({ ownership: 'shared', permissions: { canEdit: false, canDelete: false, canShare: false } }),
    );
    renderPage();

    expect(await screen.findByText(/condiviso con te in sola lettura/)).toBeInTheDocument();
    expect(screen.queryByLabelText(/Litri/)).not.toBeInTheDocument();
  });

  it('veicolo archiviato: avviso e nessun form', async () => {
    mockApi(async () => vehicle({ archivedAt: '2026-09-01T10:00:00+02:00' }));
    renderPage();

    expect(await screen.findByText(/Questo veicolo è archiviato/)).toBeInTheDocument();
    expect(screen.queryByLabelText(/Litri/)).not.toBeInTheDocument();
  });

  it('veicolo modificabile: il form compare precompilato', async () => {
    mockApi(async () => vehicle());
    renderPage();

    expect(await screen.findByLabelText(/Litri/)).toHaveValue('40.00');
  });
});
