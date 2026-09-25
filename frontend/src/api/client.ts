import { useAuthStore } from '@/stores/useAuthStore';

const API_BASE = import.meta.env.VITE_API_URL ?? '';

export class ApiError extends Error {
  constructor(
    public readonly title: string,
    public readonly status: number,
    public readonly detail?: string,
    public readonly errors?: Array<{ field: string; message: string }>,
  ) {
    super(`${title} (HTTP ${status})`);
  }
}

interface AuthFetchOptions extends RequestInit {
  /** Skip auth refresh attempt on 401 (used internally during refresh) */
  skipRefresh?: boolean;
}

let refreshPromise: Promise<boolean> | null = null;

async function refreshAccessToken(): Promise<boolean> {
  // Coalesce simultaneous refresh attempts
  if (refreshPromise) return refreshPromise;

  refreshPromise = (async () => {
    try {
      const res = await fetch(`${API_BASE}/api/auth/refresh`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'X-Client-Type': 'web' },
      });
      if (!res.ok) return false;
      const data = (await res.json()) as { access_token: string };
      useAuthStore.getState().setAccessToken(data.access_token);
      return true;
    } catch {
      return false;
    } finally {
      refreshPromise = null;
    }
  })();

  return refreshPromise;
}

function withAuth(opts: AuthFetchOptions): RequestInit {
  const access = useAuthStore.getState().accessToken;
  const headers = new Headers(opts.headers);
  headers.set('X-Client-Type', 'web');
  if (access) headers.set('Authorization', `Bearer ${access}`);
  return {
    ...opts,
    credentials: 'include',
    headers,
  };
}

/**
 * Fetch autenticato con retry su 401 (refresh + retry una volta).
 * Ritorna la Response grezza; lancia ApiError su non-2xx.
 */
async function authFetchResponse(path: string, opts: AuthFetchOptions): Promise<Response> {
  let res = await fetch(`${API_BASE}${path}`, withAuth(opts));

  if (res.status === 401 && !opts.skipRefresh) {
    const refreshed = await refreshAccessToken();
    if (refreshed) {
      res = await fetch(`${API_BASE}${path}`, withAuth(opts));
    } else {
      useAuthStore.getState().logout();
      throw new ApiError('auth.session_expired', 401);
    }
  }

  if (!res.ok) {
    let problem: {
      title?: string;
      status?: number;
      detail?: string;
      errors?: Array<{ field: string; message: string }>;
    } = {};
    try {
      problem = await res.json();
    } catch {
      /* response non-JSON */
    }
    throw new ApiError(
      problem.title ?? `http.${res.status}`,
      res.status,
      problem.detail,
      problem.errors,
    );
  }

  return res;
}

/**
 * Fetch autenticato che ritorna JSON tipizzato (o undefined su 204).
 * Lancia ApiError su non-2xx.
 */
export async function authFetch<T = unknown>(
  path: string,
  opts: AuthFetchOptions = {},
): Promise<T> {
  const res = await authFetchResponse(path, opts);
  if (res.status === 204) return undefined as T;
  return res.json() as Promise<T>;
}

/**
 * Fetch autenticato che ritorna un Blob — usato per download allegati, dove
 * `<img src>` non può inviare l'header Bearer. Stessa auth/retry di authFetch.
 */
export async function authFetchBlob(path: string, opts: AuthFetchOptions = {}): Promise<Blob> {
  const res = await authFetchResponse(path, opts);
  return res.blob();
}
