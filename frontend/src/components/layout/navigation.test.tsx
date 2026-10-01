import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { authFetch } from '@/api/client';
import { BottomNav } from './BottomNav';
import { Sidebar } from './Sidebar';

vi.mock('@/api/client', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/client')>()),
  authFetch: vi.fn(),
}));
const mockedAuthFetch = vi.mocked(authFetch);

function renderWithProviders(ui: React.ReactNode) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <MemoryRouter>{ui}</MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('navigazione', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    mockedAuthFetch.mockResolvedValue([]);
    await i18n.changeLanguage('it');
  });

  it('la sidebar desktop mostra solo Dashboard e Veicoli: i record si gestiscono dal veicolo', () => {
    renderWithProviders(<Sidebar />);

    const nav = within(screen.getByRole('navigation', { name: 'Primary' }));
    expect(nav.getAllByRole('link').map((link) => link.getAttribute('href'))).toEqual(['/', '/vehicles']);
    for (const label of ['Manutenzioni', 'Rifornimenti', 'Spese', 'Promemoria']) {
      expect(nav.queryByText(label)).not.toBeInTheDocument();
    }
  });

  it('la barra mobile resta Home, "+" e Promemoria', () => {
    renderWithProviders(<BottomNav />);

    const nav = within(screen.getByRole('navigation', { name: 'Primary mobile' }));
    expect(nav.getAllByRole('link').map((link) => link.getAttribute('href'))).toEqual(['/', '/reminders']);
    expect(nav.getByRole('button', { name: 'Aggiungi' })).toBeInTheDocument();
  });
});
