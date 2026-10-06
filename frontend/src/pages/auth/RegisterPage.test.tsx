import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { ApiError, authFetch } from '@/api/client';
import { RegisterPage } from './RegisterPage';

vi.mock('@/api/client', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/client')>()),
  authFetch: vi.fn(),
}));
const mockedAuthFetch = vi.mocked(authFetch);

function renderPage() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <MemoryRouter>
        <RegisterPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

async function fillAndSubmit(user: ReturnType<typeof userEvent.setup>) {
  await user.type(screen.getByLabelText(/^Nome/), 'Anna');
  await user.type(screen.getByLabelText(/^Cognome/), 'Neri');
  await user.type(screen.getByLabelText(/^Email/), 'anna@test.it');
  await user.type(screen.getByLabelText(/^Password/), 'segreta-123');
  await user.type(screen.getByLabelText(/^Conferma password/), 'segreta-123');
  await user.click(screen.getByRole('button', { name: 'Crea account' }));
}

describe('RegisterPage — errori del server', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
  });

  it('un 422 su password compare sotto il campo, senza alert generico', async () => {
    mockedAuthFetch.mockRejectedValue(
      new ApiError('validation_failed', 422, undefined, [{ field: 'password', message: 'common.too_long' }]),
    );
    const user = userEvent.setup();
    renderPage();
    await fillAndSubmit(user);

    expect(await screen.findByText('Troppo lungo')).toBeInTheDocument();
    // l'unico role=alert è il messaggio del campo, non l'Alert generico
    expect(screen.getAllByRole('alert')).toHaveLength(1);
    expect(screen.getByLabelText(/^Password/)).toBeInvalid();
  });

  it('un errore su un campo senza input visibile mostra l\'alert generico', async () => {
    mockedAuthFetch.mockRejectedValue(
      new ApiError('validation_failed', 422, undefined, [{ field: 'organizationName', message: 'common.too_long' }]),
    );
    const user = userEvent.setup();
    renderPage();
    await fillAndSubmit(user);

    expect(await screen.findByRole('alert')).toBeInTheDocument();
    expect(screen.queryByText('Troppo lungo')).not.toBeInTheDocument();
  });

  it('un errore non di validazione mostra l\'alert generico', async () => {
    mockedAuthFetch.mockRejectedValue(new ApiError('http.500', 500));
    const user = userEvent.setup();
    renderPage();
    await fillAndSubmit(user);

    expect(await screen.findByRole('alert')).toBeInTheDocument();
  });

  it('email già usata resta sul campo email, senza alert generico', async () => {
    mockedAuthFetch.mockRejectedValue(new ApiError('auth.email_taken', 409));
    const user = userEvent.setup();
    renderPage();
    await fillAndSubmit(user);

    const emailError = await screen.findByText(i18n.t('errors.auth.email_taken'));
    expect(emailError).toBeInTheDocument();
    expect(screen.getAllByRole('alert')).toHaveLength(1);
    expect(screen.getByLabelText(/^Email/)).toBeInvalid();
  });
});
