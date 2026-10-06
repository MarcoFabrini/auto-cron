import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { LoginPage } from './LoginPage';
import { authFetch } from '@/api/client';

vi.mock('@/api/client', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/client')>()),
  authFetch: vi.fn(),
}));
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

describe('LoginPage — invio', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
    mockedAuthFetch.mockImplementation(async (path) => {
      if (path === '/api/auth/registration') return { open: false };
      throw new Error('login fallito');
    });
  });

  function loginCalls() {
    return mockedAuthFetch.mock.calls.filter(([path]) => path === '/api/auth/login');
  }

  it("invia l'email ripulita dagli spazi", async () => {
    const user = userEvent.setup();
    renderLogin();

    await user.type(screen.getByLabelText(/^Email/), '  marco@test.it  ');
    await user.type(screen.getByLabelText(/^Password/), 'segreta');
    await user.click(screen.getByRole('button', { name: 'Entra' }));

    await waitFor(() => expect(loginCalls()).toHaveLength(1));
    expect(JSON.parse(String(loginCalls()[0]?.[1]?.body))).toEqual({ email: 'marco@test.it', password: 'segreta' });
  });

  it('con campi vuoti non chiama il server e segnala i campi obbligatori', async () => {
    const user = userEvent.setup();
    renderLogin();

    await user.click(screen.getByRole('button', { name: 'Entra' }));

    expect(await screen.findByText('Inserisci la password')).toBeInTheDocument();
    expect(screen.getByLabelText(/^Email/)).toBeInvalid();
    expect(loginCalls()).toHaveLength(0);
  });

  it("un'email di soli spazi è considerata vuota", async () => {
    const user = userEvent.setup();
    renderLogin();

    await user.type(screen.getByLabelText(/^Email/), '   ');
    await user.type(screen.getByLabelText(/^Password/), 'segreta');
    await user.click(screen.getByRole('button', { name: 'Entra' }));

    expect(await screen.findByText(i18n.t('errors.account.email.required'))).toBeInTheDocument();
    expect(loginCalls()).toHaveLength(0);
  });
});

describe('LoginPage — bersagli touch', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
  });

  it('"Password dimenticata?" e "Registrati" raggiungono i 44px (jsdom non ha layout: si verifica la classe)', async () => {
    mockedAuthFetch.mockResolvedValue({ open: true });
    renderLogin();

    expect(await screen.findByRole('link', { name: 'Registrati' })).toHaveClass('min-h-touch', 'min-w-touch');
    expect(screen.getByRole('link', { name: 'Password dimenticata?' })).toHaveClass('min-h-touch', 'min-w-touch');
  });
});
