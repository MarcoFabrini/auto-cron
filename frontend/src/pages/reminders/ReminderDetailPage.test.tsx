import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { authFetch } from '@/api/client';
import type { ReminderResponse } from '@/api/types/reminder';
import { ReminderDetailPage } from './ReminderDetailPage';

vi.mock('@/api/client', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/client')>()),
  authFetch: vi.fn(),
}));
const mockedAuthFetch = vi.mocked(authFetch);

// Completato alle 00:30 del 1 ottobre a Roma (UTC+2): in UTC è ancora il 30 settembre.
const completed: ReminderResponse = {
  id: 3,
  vehicle: { id: 7, name: 'Golf', brand: 'VW', model: 'Golf' },
  type: 'inspection',
  description: 'Revisione',
  dueDate: null,
  dueKm: null,
  notifyDaysBefore: 7,
  completedAt: '2026-09-30T22:30:00+00:00',
};

function renderPage() {
  mockedAuthFetch.mockImplementation(async (path: string) => {
    if (path === '/api/reminders/3') return completed;
    if (path === '/api/vehicles/7') {
      return { id: 7, permissions: { canEdit: false, canDelete: false, canShare: false } };
    }
    return [];
  });
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <MemoryRouter initialEntries={['/reminders/3']}>
        <Routes>
          <Route path="/reminders/:id" element={<ReminderDetailPage />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('ReminderDetailPage — data di completamento', () => {
  const originalTz = process.env.TZ;

  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
    process.env.TZ = 'Europe/Rome';
  });

  afterEach(() => {
    if (originalTz === undefined) delete process.env.TZ;
    else process.env.TZ = originalTz;
  });

  it('mostra il giorno locale, non quello UTC', async () => {
    renderPage();

    expect(await screen.findByText(/^01 ott 2026$/)).toBeInTheDocument();
    expect(screen.queryByText(/30 set/)).not.toBeInTheDocument();
  });
});
