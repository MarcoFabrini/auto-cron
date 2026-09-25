import type { ReactNode } from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { usePushSubscription } from './usePushSubscription';
import { authFetch } from '@/api/client';

vi.mock('@/api/client', () => ({ authFetch: vi.fn() }));

const mockedAuthFetch = vi.mocked(authFetch);

function wrapper({ children }: { children: ReactNode }) {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return <QueryClientProvider client={qc}>{children}</QueryClientProvider>;
}

describe('usePushSubscription', () => {
  beforeEach(() => {
    mockedAuthFetch.mockReset();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    // serviceWorker eventualmente iniettato dai test
    if ('serviceWorker' in navigator) {
      // @ts-expect-error cleanup del property override di test
      delete navigator.serviceWorker;
    }
  });

  it('chiede la VAPID public key a runtime al mount', async () => {
    mockedAuthFetch.mockResolvedValue({ publicKey: 'abc' });
    renderHook(() => usePushSubscription(), { wrapper });
    await waitFor(() =>
      expect(mockedAuthFetch).toHaveBeenCalledWith('/api/push-subscriptions/vapid-public-key'),
    );
  });

  it('ritorna "unsupported" senza service worker / PushManager (jsdom)', async () => {
    mockedAuthFetch.mockResolvedValue({ publicKey: 'abc' });
    const { result } = renderHook(() => usePushSubscription(), { wrapper });
    await waitFor(() => expect(result.current.status).toBe('unsupported'));
  });

  it('ritorna "not-configured" quando la chiave runtime è vuota', async () => {
    // Simula un browser che supporta Push ma istanza senza VAPID configurata.
    vi.stubGlobal('PushManager', class {});
    Object.defineProperty(navigator, 'serviceWorker', {
      configurable: true,
      value: { ready: new Promise(() => {}) },
    });
    mockedAuthFetch.mockResolvedValue({ publicKey: '' });

    const { result } = renderHook(() => usePushSubscription(), { wrapper });
    await waitFor(() => expect(result.current.status).toBe('not-configured'));
  });

  describe('con Push API presente e chiave VAPID configurata', () => {
    function installServiceWorker(sw: Record<string, unknown>) {
      vi.stubGlobal('PushManager', class {});
      vi.stubGlobal('Notification', { permission: 'default' });
      // jsdom non implementa isSecureContext (nei browser reali è sempre definito).
      vi.stubGlobal('isSecureContext', true);
      Object.defineProperty(navigator, 'serviceWorker', { configurable: true, value: sw });
      mockedAuthFetch.mockResolvedValue({ publicKey: 'abc' });
    }

    function activeRegistration(subscription: unknown) {
      return { active: {}, pushManager: { getSubscription: vi.fn().mockResolvedValue(subscription) } };
    }

    it('"unsubscribed" (pulsante abilita visibile) con SW attivo e nessuna subscription', async () => {
      installServiceWorker({
        getRegistration: vi.fn().mockResolvedValue(activeRegistration(null)),
        register: vi.fn(),
        ready: new Promise(() => {}),
      });

      const { result } = renderHook(() => usePushSubscription(), { wrapper });
      await waitFor(() => expect(result.current.status).toBe('unsubscribed'));
    });

    it('"subscribed" se il browser ha già una subscription', async () => {
      installServiceWorker({
        getRegistration: vi.fn().mockResolvedValue(activeRegistration({ endpoint: 'https://push' })),
        register: vi.fn(),
        ready: new Promise(() => {}),
      });

      const { result } = renderHook(() => usePushSubscription(), { wrapper });
      await waitFor(() => expect(result.current.status).toBe('subscribed'));
    });

    it('se nessun SW è registrato lo registra da sé e usa `ready` (non resta muto)', async () => {
      const reg = activeRegistration(null);
      const register = vi.fn().mockResolvedValue({ active: null });
      installServiceWorker({
        getRegistration: vi.fn().mockResolvedValue(undefined),
        register,
        ready: Promise.resolve(reg),
      });

      const { result } = renderHook(() => usePushSubscription(), { wrapper });
      await waitFor(() => expect(result.current.status).toBe('unsubscribed'));
      expect(register).toHaveBeenCalledWith('/sw.js', { scope: '/' });
    });

    it('"sw-unavailable" (non "unsupported") se la registrazione fallisce', async () => {
      // Es. certificato non fidato / contesto insicuro: register() lancia SecurityError.
      installServiceWorker({
        getRegistration: vi.fn().mockResolvedValue(undefined),
        register: vi.fn().mockRejectedValue(new DOMException('insecure', 'SecurityError')),
        ready: new Promise(() => {}),
      });

      const { result } = renderHook(() => usePushSubscription(), { wrapper });
      await waitFor(() => expect(result.current.status).toBe('sw-unavailable'));
    });

    it('"sw-unavailable" senza secure context, senza nemmeno tentare la registrazione', async () => {
      const register = vi.fn();
      installServiceWorker({
        getRegistration: vi.fn().mockResolvedValue(undefined),
        register,
        ready: new Promise(() => {}),
      });
      vi.stubGlobal('isSecureContext', false);

      const { result } = renderHook(() => usePushSubscription(), { wrapper });
      await waitFor(() => expect(result.current.status).toBe('sw-unavailable'));
      expect(register).not.toHaveBeenCalled();
    });
  });

  it('ritorna "not-configured" anche se la fetch della chiave fallisce', async () => {
    vi.stubGlobal('PushManager', class {});
    Object.defineProperty(navigator, 'serviceWorker', {
      configurable: true,
      value: { ready: new Promise(() => {}) },
    });
    mockedAuthFetch.mockRejectedValue(new Error('boom'));

    const { result } = renderHook(() => usePushSubscription(), { wrapper });
    await waitFor(() => expect(result.current.status).toBe('not-configured'));
  });
});
