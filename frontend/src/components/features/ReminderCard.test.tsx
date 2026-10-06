import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { authFetch } from '@/api/client';
import type { Reminder } from '@/api/types/reminder';
import { ReminderCard } from './ReminderCard';

vi.mock('@/api/client', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/client')>()),
  authFetch: vi.fn(),
}));
const mockedAuthFetch = vi.mocked(authFetch);

const reminder = (overrides: Partial<Reminder>): Reminder => ({
  id: 1,
  vehicleId: 7,
  type: 'service',
  description: 'Tagliando',
  dueDate: null,
  dueKm: null,
  notifyDaysBefore: 7,
  completedAt: null,
  ...overrides,
});

function renderCard(r: Reminder) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <MemoryRouter>
        <ReminderCard reminder={r} />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('ReminderCard', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    mockedAuthFetch.mockResolvedValue([]);
    await i18n.changeLanguage('it');
  });

  it('con scadenza a 0 km mostra "0 km", non uno "0" nudo', () => {
    renderCard(reminder({ dueKm: 0 }));

    expect(screen.getByText('0 km')).toBeInTheDocument();
    expect(screen.queryByText('0')).not.toBeInTheDocument();
  });

  it('data e km a 0: entrambi con il separatore', () => {
    renderCard(reminder({ dueDate: '2026-12-01', dueKm: 0 }));

    expect(screen.getByText('0 km')).toBeInTheDocument();
    expect(screen.getByText('·')).toBeInTheDocument();
    expect(screen.queryByText('0')).not.toBeInTheDocument();
  });

  it('senza km di scadenza non mostra nulla accanto alla data', () => {
    renderCard(reminder({ dueDate: '2026-12-01', dueKm: null }));

    expect(screen.queryByText('·')).not.toBeInTheDocument();
    expect(screen.queryByText(/km$/)).not.toBeInTheDocument();
  });
});
