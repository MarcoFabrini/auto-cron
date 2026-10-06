import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useState } from 'react';
import i18n from '@/i18n';
import { authFetch } from '@/api/client';
import { useAuthStore, type User } from '@/stores/useAuthStore';
import { EditProfileDialog } from './EditProfileDialog';

vi.mock('@/api/client', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/client')>()),
  authFetch: vi.fn(),
  authFetchBlob: vi.fn(),
}));
const mockedAuthFetch = vi.mocked(authFetch);

const baseUser: User = {
  id: 1,
  email: 'marco@test.it',
  firstName: 'Marco',
  lastName: 'Rossi',
  locale: 'it',
  hasAvatar: false,
  emailVerified: true,
  isInstanceAdmin: false,
  memberships: [],
};

/** Come SettingsPage: `user` arriva dallo store, quindi cambia identità a ogni setUser. */
function Harness() {
  const user = useAuthStore((s) => s.user);
  const [open, setOpen] = useState(true);
  if (!user) return null;
  return (
    <>
      <button type="button" onClick={() => setOpen(true)}>
        riapri
      </button>
      <EditProfileDialog open={open} onOpenChange={setOpen} user={user} />
    </>
  );
}

function renderDialog() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <Harness />
    </QueryClientProvider>,
  );
}

describe('EditProfileDialog', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
    useAuthStore.setState({ status: 'authenticated', accessToken: 'tok', user: baseUser });
  });

  it("caricare la foto non cancella il nome digitato e non ancora salvato", async () => {
    mockedAuthFetch.mockResolvedValue({ hasAvatar: true });
    const user = userEvent.setup();
    renderDialog();

    const firstName = screen.getByLabelText(/Nome/);
    await user.clear(firstName);
    await user.type(firstName, 'Giorgio');

    await user.upload(
      document.querySelector<HTMLInputElement>('input[type="file"]')!,
      new File(['x'], 'foto.png', { type: 'image/png' }),
    );

    await waitFor(() => expect(useAuthStore.getState().user?.hasAvatar).toBe(true));
    expect(mockedAuthFetch).toHaveBeenCalledWith('/api/auth/avatar', expect.objectContaining({ method: 'POST' }));
    expect(screen.getByLabelText(/Nome/)).toHaveValue('Giorgio');
  });

  it('rimuovere la foto non cancella il nome digitato e non ancora salvato', async () => {
    useAuthStore.setState({ user: { ...baseUser, hasAvatar: true } });
    mockedAuthFetch.mockResolvedValue(undefined);
    const user = userEvent.setup();
    renderDialog();

    const firstName = screen.getByLabelText(/Nome/);
    await user.clear(firstName);
    await user.type(firstName, 'Giorgio');

    await user.click(screen.getByRole('button', { name: 'Rimuovi' }));

    await waitFor(() => expect(useAuthStore.getState().user?.hasAvatar).toBe(false));
    expect(mockedAuthFetch).toHaveBeenCalledWith('/api/auth/avatar', expect.objectContaining({ method: 'DELETE' }));
    expect(screen.getByLabelText(/Nome/)).toHaveValue('Giorgio');
  });

  it('riaprendo il dialog mostra i valori salvati, non le modifiche scartate', async () => {
    const user = userEvent.setup();
    renderDialog();

    const firstName = screen.getByLabelText(/Nome/);
    await user.clear(firstName);
    await user.type(firstName, 'Giorgio');

    await user.click(screen.getByRole('button', { name: 'Annulla' }));
    expect(screen.queryByLabelText(/Nome/)).not.toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'riapri' }));
    expect(await screen.findByLabelText(/Nome/)).toHaveValue('Marco');
  });
});
