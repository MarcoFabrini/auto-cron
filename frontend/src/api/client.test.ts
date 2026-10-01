import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError, authFetch, refreshAccessToken } from '@/api/client';
import { useAuthStore, type User } from '@/stores/useAuthStore';

const user = { id: 1, locale: 'it' } as User;

function response(status: number, body: unknown = {}): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

describe('api client — sessione e refresh', () => {
  const fetchMock = vi.fn();

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
    useAuthStore.getState().setAuthenticated('old-token', user);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('refresh riuscito: salva il nuovo token e riprova la richiesta una volta', async () => {
    fetchMock
      .mockResolvedValueOnce(response(401))
      .mockResolvedValueOnce(response(200, { access_token: 'new-token' }))
      .mockResolvedValueOnce(response(200, { ok: true }));

    await expect(authFetch('/api/vehicles')).resolves.toEqual({ ok: true });

    expect(useAuthStore.getState().accessToken).toBe('new-token');
    expect(useAuthStore.getState().status).toBe('authenticated');
  });

  it('refresh rifiutato (401): sessione finita → logout', async () => {
    fetchMock.mockResolvedValueOnce(response(401)).mockResolvedValueOnce(response(401));

    await expect(authFetch('/api/vehicles')).rejects.toMatchObject({ title: 'auth.session_expired' });

    expect(useAuthStore.getState().status).toBe('unauthenticated');
  });

  it.each([500, 502, 503])('refresh con HTTP %i: NON fa logout (server in difficoltà)', async (status) => {
    fetchMock.mockResolvedValueOnce(response(401)).mockResolvedValueOnce(response(status));

    await expect(authFetch('/api/vehicles')).rejects.toBeInstanceOf(ApiError);

    expect(useAuthStore.getState().status).toBe('authenticated');
    expect(useAuthStore.getState().accessToken).toBe('old-token');
  });

  it('refresh con rete assente: NON fa logout', async () => {
    fetchMock.mockResolvedValueOnce(response(401)).mockRejectedValueOnce(new TypeError('Failed to fetch'));

    await expect(authFetch('/api/vehicles')).rejects.toMatchObject({ title: 'network.unavailable' });

    expect(useAuthStore.getState().status).toBe('authenticated');
  });

  it('refresh simultanei vengono coalescenti in una sola chiamata', async () => {
    fetchMock.mockResolvedValue(response(200, { access_token: 'shared' }));

    const [a, b] = await Promise.all([refreshAccessToken(), refreshAccessToken()]);

    expect(a).toBe('ok');
    expect(b).toBe('ok');
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('esito del refresh: 400/401 invalid, 5xx e rete unavailable', async () => {
    fetchMock.mockResolvedValueOnce(response(400));
    expect(await refreshAccessToken()).toBe('invalid');

    fetchMock.mockResolvedValueOnce(response(503));
    expect(await refreshAccessToken()).toBe('unavailable');

    fetchMock.mockRejectedValueOnce(new TypeError('offline'));
    expect(await refreshAccessToken()).toBe('unavailable');
  });
});
