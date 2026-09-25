import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { authFetch } from '@/api/client';
import { UpcomingRemindersCard } from './UpcomingRemindersCard';

vi.mock('@/api/client', () => ({ authFetch: vi.fn() }));
const mockedAuthFetch = vi.mocked(authFetch);

const vehicle = (id: number, name: string) => ({
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
});

const reminderResponse = (id: number, vehicleId: number, dueDate: string) => ({
  id,
  vehicle: { id: vehicleId, name: 'Panda', brand: 'Fiat', model: 'Panda' },
  type: 'inspection',
  description: 'Revisione biennale',
  dueDate,
  dueKm: null,
  notifyDaysBefore: 30,
  completedAt: null,
});

function renderCard() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <MemoryRouter>
        <UpcomingRemindersCard />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('UpcomingRemindersCard', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
  });

  it('mostra le scadenze in arrivo con nome veicolo e badge urgenza', async () => {
    mockedAuthFetch.mockImplementation((url: string) => {
      if (url.includes('/api/reminders/upcoming')) {
        return Promise.resolve([reminderResponse(1, 4, '2026-10-01')]);
      }
      if (url.includes('/api/vehicles')) {
        return Promise.resolve([vehicle(4, 'Panda')]);
      }
      throw new Error(`unexpected url: ${url}`);
    });
    renderCard();

    expect(await screen.findByText('Revisione biennale')).toBeInTheDocument();
    expect(await screen.findByText('Panda')).toBeInTheDocument();
  });

  it('nessuna scadenza in arrivo: messaggio vuoto, niente card', async () => {
    mockedAuthFetch.mockImplementation((url: string) => {
      if (url.includes('/api/reminders/upcoming')) return Promise.resolve([]);
      if (url.includes('/api/vehicles')) return Promise.resolve([]);
      throw new Error(`unexpected url: ${url}`);
    });
    renderCard();

    expect(await screen.findByText('Nessuna scadenza nei prossimi 30 giorni.')).toBeInTheDocument();
  });
});
