import type { ReactNode } from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { useVerifyEmail, useResendVerification, useAcceptInvitation } from './useAccount';
import { useAuthStore } from '@/stores/useAuthStore';
import { authFetch } from '@/api/client';

vi.mock('@/api/client', () => ({ authFetch: vi.fn() }));

const mockedAuthFetch = vi.mocked(authFetch);

function wrapper({ children }: { children: ReactNode }) {
  const qc = new QueryClient({ defaultOptions: { mutations: { retry: false } } });
  return <QueryClientProvider client={qc}>{children}</QueryClientProvider>;
}

describe('useAccount mutations', () => {
  beforeEach(() => {
    mockedAuthFetch.mockReset();
    useAuthStore.setState({ user: null });
  });

  it('useVerifyEmail posts the token to the verify endpoint', async () => {
    mockedAuthFetch.mockResolvedValue(undefined);
    const { result } = renderHook(() => useVerifyEmail(), { wrapper });

    result.current.mutate({ token: 'tok-1' });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(mockedAuthFetch).toHaveBeenCalledWith(
      '/api/auth/verify-email',
      expect.objectContaining({ method: 'POST', body: JSON.stringify({ token: 'tok-1' }) }),
    );
  });

  it('useResendVerification posts to the resend endpoint', async () => {
    mockedAuthFetch.mockResolvedValue(undefined);
    const { result } = renderHook(() => useResendVerification(), { wrapper });

    result.current.mutate();

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(mockedAuthFetch).toHaveBeenCalledWith(
      '/api/auth/resend-verification',
      expect.objectContaining({ method: 'POST' }),
    );
  });

  it('useAcceptInvitation accepts then reloads /me into the store', async () => {
    const me = { id: 1, email: 'a@b.it', firstName: 'A', emailVerified: true } as never;
    mockedAuthFetch.mockResolvedValueOnce(undefined).mockResolvedValueOnce(me);
    const { result } = renderHook(() => useAcceptInvitation(), { wrapper });

    result.current.mutate({ token: 'inv-1' });

    await waitFor(() => expect(result.current.isSuccess).toBe(true));
    expect(mockedAuthFetch).toHaveBeenNthCalledWith(
      1,
      '/api/auth/invitation/accept',
      expect.objectContaining({ method: 'POST', body: JSON.stringify({ token: 'inv-1' }) }),
    );
    expect(mockedAuthFetch).toHaveBeenNthCalledWith(2, '/api/auth/me');
    expect(useAuthStore.getState().user).toBe(me);
  });
});
