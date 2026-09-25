import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { LoginPage } from './LoginPage';
import { authFetch } from '@/api/client';

vi.mock('@/api/client', () => ({ authFetch: vi.fn() }));
const mockedAuthFetch = vi.mocked(authFetch);

function renderLogin() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <MemoryRouter>
        <LoginPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

/** Il link "Registrati" compare solo a istanza vuota (primo avvio): poi si entra su invito. */
describe('LoginPage — link di registrazione', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
  });

  it('istanza vuota: mostra il link Registrati', async () => {
    mockedAuthFetch.mockResolvedValue({ open: true });
    renderLogin();

    expect(await screen.findByRole('link', { name: 'Registrati' })).toHaveAttribute('href', '/register');
  });

  it('istanza con utenti: nessun link né testo di registrazione', async () => {
    mockedAuthFetch.mockResolvedValue({ open: false });
    renderLogin();

    await waitFor(() => expect(mockedAuthFetch).toHaveBeenCalledWith('/api/auth/registration', { skipRefresh: true }));
    expect(screen.queryByRole('link', { name: 'Registrati' })).not.toBeInTheDocument();
    expect(screen.queryByText(/Non hai un account/)).not.toBeInTheDocument();
  });

  it('stato non verificabile (errore): il link resta nascosto', async () => {
    mockedAuthFetch.mockRejectedValue(new Error('rete'));
    renderLogin();

    await waitFor(() => expect(mockedAuthFetch).toHaveBeenCalled());
    expect(screen.queryByRole('link', { name: 'Registrati' })).not.toBeInTheDocument();
  });

  it('mentre controlla non mostra il link (niente flash su istanza già chiusa)', () => {
    mockedAuthFetch.mockReturnValue(new Promise(() => {}));
    renderLogin();

    expect(screen.queryByRole('link', { name: 'Registrati' })).not.toBeInTheDocument();
  });
});
