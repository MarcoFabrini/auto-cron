import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { RouterProvider } from 'react-router-dom';
import './index.css';
import './i18n';
import './schemas/errorMap';
import { router } from './router';
import { Toaster } from '@/components/ui';
import { AuthGate } from '@/components/layout';
import { ErrorBoundary } from '@/components/ErrorBoundary';
import { useAuthStore } from '@/stores/useAuthStore';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      refetchOnWindowFocus: false,
      retry: (failureCount, error: unknown) => {
        // Niente retry su errori auth (401/403) o validation (422)
        if (error instanceof Error && 'status' in error) {
          const status = (error as { status: number }).status;
          if ([401, 403, 422, 429].includes(status)) return false;
        }
        return failureCount < 2;
      },
    },
  },
});

// Cache di un altro utente mai visibile: si svuota quando la sessione finisce (logout, sessione
// scaduta, refresh rifiutato) o cambia utente, in un unico punto invece che nei singoli hook.
useAuthStore.subscribe((state, prev) => {
  if (prev.user && (!state.user || state.user.id !== prev.user.id)) {
    queryClient.clear();
  }
});

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <ErrorBoundary>
      <QueryClientProvider client={queryClient}>
        <AuthGate>
          <RouterProvider router={router} />
        </AuthGate>
        <Toaster />
      </QueryClientProvider>
    </ErrorBoundary>
  </StrictMode>,
);
