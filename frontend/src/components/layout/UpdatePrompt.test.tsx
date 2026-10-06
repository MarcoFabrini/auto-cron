import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Toaster } from '@/components/ui';
import { UpdatePrompt } from './UpdatePrompt';
import i18n from '@/i18n';
import * as pwaUpdate from '@/lib/pwaUpdate';

describe('UpdatePrompt', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it');
    Element.prototype.hasPointerCapture = () => false;
    pwaUpdate.resetUpdateStateForTests();
  });
  afterEach(() => {
    pwaUpdate.resetUpdateStateForTests();
  });

  it('non mostra nulla finché non c\'è una nuova versione', () => {
    render(
      <>
        <Toaster />
        <UpdatePrompt />
      </>,
    );
    expect(screen.queryByText('Nuova versione disponibile')).not.toBeInTheDocument();
  });

  it('con un nuovo worker mostra l\'avviso e "Aggiorna" ricarica, senza ricaricare da solo', async () => {
    const refresh = vi.spyOn(pwaUpdate, 'refreshApp').mockResolvedValue(undefined);
    const containerHandlers = new Map<string, () => void>();
    pwaUpdate.watchForUpdates(
      { update: vi.fn(), waiting: null },
      {
        container: {
          controller: {} as ServiceWorker,
          addEventListener: (type: string, cb: () => void) => containerHandlers.set(type, cb),
          removeEventListener: () => {},
        },
        doc: { visibilityState: 'visible', addEventListener: () => {}, removeEventListener: () => {} },
        setInterval: () => 1,
        clearInterval: () => {},
      } as unknown as pwaUpdate.WatchEnv,
    );
    render(
      <>
        <Toaster />
        <UpdatePrompt />
      </>,
    );

    act(() => containerHandlers.get('controllerchange')?.());
    expect(await screen.findByText('Nuova versione disponibile')).toBeInTheDocument();
    expect(refresh).not.toHaveBeenCalled();

    await userEvent.click(screen.getByRole('button', { name: 'Aggiorna' }));
    expect(refresh).toHaveBeenCalledTimes(1);
  });
});
