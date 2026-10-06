import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { ApiError, authFetch } from '@/api/client';
import type { Vehicle } from '@/api/types/vehicle';
import { VehicleUsableGate } from './VehicleUsableGate';

vi.mock('@/api/client', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/client')>()),
  authFetch: vi.fn(),
}));
const mockedAuthFetch = vi.mocked(authFetch);

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

function renderGate() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <VehicleUsableGate vehicleId={7}>
        <p>FORM</p>
      </VehicleUsableGate>
    </QueryClientProvider>,
  );
}

describe('VehicleUsableGate', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
  });

  it('finché il veicolo carica non mostra il form', () => {
    mockedAuthFetch.mockReturnValue(new Promise(() => {}));
    const { container } = renderGate();

    expect(screen.queryByText('FORM')).not.toBeInTheDocument();
    expect(container.querySelector('.animate-pulse')).toBeInTheDocument();
  });

  it("se la query del veicolo fallisce (403) mostra l'errore, non il form", async () => {
    mockedAuthFetch.mockRejectedValue(new ApiError('http.403', 403));
    renderGate();

    expect(await screen.findByRole('alert')).toBeInTheDocument();
    expect(screen.queryByText('FORM')).not.toBeInTheDocument();
  });

  it('veicolo in sola lettura: avviso, niente form', async () => {
    mockedAuthFetch.mockResolvedValue(
      vehicle({ ownership: 'shared', permissions: { canEdit: false, canDelete: false, canShare: false } }),
    );
    renderGate();

    expect(await screen.findByText(/condiviso con te in sola lettura/)).toBeInTheDocument();
    expect(screen.queryByText('FORM')).not.toBeInTheDocument();
  });

  it('veicolo archiviato: avviso, niente form', async () => {
    mockedAuthFetch.mockResolvedValue(vehicle({ archivedAt: '2026-09-01T10:00:00+02:00' }));
    renderGate();

    expect(await screen.findByText(/Questo veicolo è archiviato/)).toBeInTheDocument();
    expect(screen.queryByText('FORM')).not.toBeInTheDocument();
  });

  it('veicolo modificabile e attivo: mostra il form', async () => {
    mockedAuthFetch.mockResolvedValue(vehicle());
    renderGate();

    expect(await screen.findByText('FORM')).toBeInTheDocument();
  });
});
