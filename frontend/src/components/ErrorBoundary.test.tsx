import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import i18n from '@/i18n';
import { mockLocationReload } from '@/test/mockReload';
import { clearChunkReloadFlag } from '@/lib/chunkRecovery';
import { ErrorBoundary } from './ErrorBoundary';

function Crash({ error }: { error: Error }): never {
  throw error;
}

describe('ErrorBoundary', () => {
  let reload: ReturnType<typeof mockLocationReload>;

  beforeEach(async () => {
    clearChunkReloadFlag();
    reload = mockLocationReload();
    vi.spyOn(console, 'error').mockImplementation(() => undefined);
    await i18n.changeLanguage('it');
  });

  it('con un errore qualsiasi mostra la schermata di errore con "Ricarica", senza ricaricare da sola', () => {
    render(
      <ErrorBoundary>
        <Crash error={new Error('boom')} />
      </ErrorBoundary>,
    );

    expect(screen.getByRole('heading', { name: 'Qualcosa è andato storto' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Ricarica' })).toBeInTheDocument();
    expect(reload).not.toHaveBeenCalled();
  });

  it('con un chunk sparito (import dinamico fallito) ricarica una volta sola', () => {
    const error = new TypeError('Failed to fetch dynamically imported module: /assets/Old-abc.js');
    const { unmount } = render(
      <ErrorBoundary>
        <Crash error={error} />
      </ErrorBoundary>,
    );
    expect(reload).toHaveBeenCalledTimes(1);
    unmount();

    render(
      <ErrorBoundary>
        <Crash error={error} />
      </ErrorBoundary>,
    );
    expect(reload).toHaveBeenCalledTimes(1);
    expect(screen.getByRole('button', { name: 'Ricarica' })).toBeInTheDocument();
  });
});
