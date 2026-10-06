import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { authFetch } from '@/api/client';
import type { Vehicle } from '@/api/types/vehicle';
import { VehicleListPage } from './VehicleListPage';

vi.mock('@/api/client', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/client')>()),
  authFetch: vi.fn(),
}));
const mockedAuthFetch = vi.mocked(authFetch);

const vehicle = (id: number, name: string, archivedAt: string | null = null): Vehicle => ({
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
  archivedAt,
  ownership: 'owned',
  permissions: { canEdit: true, canDelete: true, canShare: false },
});

function renderPage(active: Vehicle[], archived: Vehicle[]) {
  mockedAuthFetch.mockImplementation(async (path: string) => {
    if (path === '/api/vehicles') return active;
    if (path === '/api/vehicles?archived=1') return archived;
    throw new Error(`unexpected fetch ${path}`);
  });
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <MemoryRouter>
        <VehicleListPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('VehicleListPage', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
  });

  it('mostra gli archiviati in una sezione chiusa con il conteggio, separata dagli attivi', async () => {
    renderPage([vehicle(1, 'Golf')], [vehicle(7, 'Vecchia Panda', '2026-09-01T10:00:00+02:00')]);

    expect(await screen.findByText('Golf')).toBeInTheDocument();
    const toggle = await screen.findByRole('button', { name: 'Veicoli archiviati (1)' });
    expect(toggle).toHaveAttribute('aria-expanded', 'false');
    expect(screen.queryByText('Vecchia Panda')).not.toBeInTheDocument();
  });

  it('aprendo la sezione elenca gli archiviati con il badge e il link al dettaglio (dove si ripristina)', async () => {
    renderPage([vehicle(1, 'Golf')], [vehicle(7, 'Vecchia Panda', '2026-09-01T10:00:00+02:00')]);
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: 'Veicoli archiviati (1)' }));

    const link = await screen.findByRole('link', { name: /Vecchia Panda/ });
    expect(link).toHaveAttribute('href', '/vehicles/7');
    expect(link).toHaveTextContent('Archiviato');
    expect(screen.getByRole('button', { name: 'Veicoli archiviati (1)' })).toHaveAttribute('aria-expanded', 'true');
  });

  it('senza archiviati la sezione non c\'è', async () => {
    renderPage([vehicle(1, 'Golf')], []);

    expect(await screen.findByText('Golf')).toBeInTheDocument();
    expect(screen.queryByText(/Veicoli archiviati/)).not.toBeInTheDocument();
  });

  it('se la query degli archiviati fallisce la lista degli attivi resta usabile', async () => {
    mockedAuthFetch.mockImplementation(async (path: string) => {
      if (path === '/api/vehicles') return [vehicle(1, 'Golf')];
      throw new Error('boom');
    });
    const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    render(
      <QueryClientProvider client={qc}>
        <MemoryRouter>
          <VehicleListPage />
        </MemoryRouter>
      </QueryClientProvider>,
    );

    expect(await screen.findByText('Golf')).toBeInTheDocument();
    expect(screen.queryByText(/Veicoli archiviati/)).not.toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('ha esattamente un h1 (sr-only, con il nome della pagina)', async () => {
    renderPage([vehicle(1, 'Golf')], []);

    await screen.findByText('Golf');
    const h1 = screen.getAllByRole('heading', { level: 1 });
    expect(h1).toHaveLength(1);
    expect(h1[0]).toHaveTextContent('Veicoli');
  });
});
