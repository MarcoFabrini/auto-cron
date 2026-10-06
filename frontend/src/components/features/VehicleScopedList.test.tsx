import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { authFetch } from '@/api/client';
import { VehicleScopedList } from './VehicleScopedList';
import type { Vehicle } from '@/api/types/vehicle';

vi.mock('@/api/client', () => ({ authFetch: vi.fn() }));
const mockedAuthFetch = vi.mocked(authFetch);

const panda: Vehicle = {
  id: 4,
  name: 'Panda',
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
  ownership: 'owned',
  permissions: { canEdit: true, canDelete: true, canShare: true },
};

describe('VehicleScopedList', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
  });

  it('senza veicolo selezionato mostra la griglia con il suo titolo "Scegli un veicolo"', async () => {
    mockedAuthFetch.mockResolvedValue([panda]);
    const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    render(
      <QueryClientProvider client={qc}>
        <MemoryRouter>
          <VehicleScopedList
            vehicleId={0}
            onSelectVehicle={() => {}}
            items={undefined}
            isLoading={false}
            error={null}
            empty={null}
            renderItem={() => null}
          />
        </MemoryRouter>
      </QueryClientProvider>,
    );

    expect(await screen.findByText('Panda')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Scegli un veicolo' })).toBeInTheDocument();
  });
});
