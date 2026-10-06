import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { useAuthStore } from '@/stores/useAuthStore';
import { unsubscribeThisDevice } from './pushCleanup';

// Qui authFetch è quello vero: si simula solo la rete (fetch), per verificare il refresh a token scaduto.
const fetchMock = vi.fn<typeof fetch>();
const unsubscribe = vi.fn<() => Promise<boolean>>();

function installServiceWorker(withSubscription = true) {
  const sub = withSubscription ? { endpoint: 'https://push.example/abc', unsubscribe } : null;
  Object.defineProperty(navigator, 'serviceWorker', {
    configurable: true,
    value: { getRegistration: async () => ({ pushManager: { getSubscription: async () => sub } }) },
  });
}

function json(status: number, body: unknown = {}): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
}

const urls = () => fetchMock.mock.calls.map(([url]) => String(url));

describe('unsubscribeThisDevice', () => {
  beforeEach(() => {
    fetchMock.mockReset();
    unsubscribe.mockReset().mockResolvedValue(true);
    vi.stubGlobal('fetch', fetchMock);
    useAuthStore.setState({ status: 'authenticated', accessToken: 'scaduto', user: null });
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    // @ts-expect-error cleanup del property override di test
    delete navigator.serviceWorker;
  });

  it('con access token scaduto: refresh, nuova chiamata a /unsubscribe e sottoscrizione del browser chiusa', async () => {
    installServiceWorker();
    fetchMock
      .mockResolvedValueOnce(json(401, { code: 401, message: 'Expired JWT Token' }))
      .mockResolvedValueOnce(json(200, { access_token: 'nuovo' }))
      .mockResolvedValueOnce(new Response(null, { status: 204 }));

    await unsubscribeThisDevice();

    expect(urls()).toEqual([
      '/api/push-subscriptions/unsubscribe',
      '/api/auth/refresh',
      '/api/push-subscriptions/unsubscribe',
    ]);
    expect(new Headers(fetchMock.mock.calls[2]?.[1]?.headers).get('Authorization')).toBe('Bearer nuovo');
    expect(unsubscribe).toHaveBeenCalledTimes(1);
  });

  it('se il server fallisce chiude comunque la sottoscrizione del browser, senza lanciare', async () => {
    installServiceWorker();
    fetchMock.mockResolvedValue(json(500, { title: 'boom' }));

    await expect(unsubscribeThisDevice()).resolves.toBeUndefined();

    expect(unsubscribe).toHaveBeenCalledTimes(1);
  });

  it('se la rete cade chiude comunque la sottoscrizione del browser', async () => {
    installServiceWorker();
    fetchMock.mockRejectedValue(new TypeError('Failed to fetch'));

    await expect(unsubscribeThisDevice()).resolves.toBeUndefined();

    expect(unsubscribe).toHaveBeenCalledTimes(1);
  });

  it('senza sottoscrizione non chiama il server', async () => {
    installServiceWorker(false);

    await unsubscribeThisDevice();

    expect(fetchMock).not.toHaveBeenCalled();
    expect(unsubscribe).not.toHaveBeenCalled();
  });
});
