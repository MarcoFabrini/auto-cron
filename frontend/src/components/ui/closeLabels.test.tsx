import { beforeEach, describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import i18n from '@/i18n';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from './Dialog';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from './Sheet';
import { Toast, ToastClose, ToastProvider, ToastViewport } from './Toast';

// Il pulsante di chiusura dei tre overlay condivisi usa `actions.close`, non un "Close" fisso, e
// ha un bersaglio da 44px (jsdom non ha layout: si verifica la classe che lo garantisce).
const touch = ['min-h-touch', 'min-w-touch'];
describe('pulsanti di chiusura degli overlay', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it');
  });

  it('il Dialog ha "Chiudi"', () => {
    render(
      <Dialog open>
        <DialogContent>
          <DialogTitle>Titolo</DialogTitle>
          <DialogDescription>Descrizione</DialogDescription>
        </DialogContent>
      </Dialog>,
    );
    expect(screen.getByRole('button', { name: 'Chiudi' })).toHaveClass(...touch);
  });

  it('lo Sheet ha "Chiudi"', () => {
    render(
      <Sheet open>
        <SheetContent>
          <SheetTitle>Titolo</SheetTitle>
          <SheetDescription>Descrizione</SheetDescription>
        </SheetContent>
      </Sheet>,
    );
    expect(screen.getByRole('button', { name: 'Chiudi' })).toHaveClass(...touch);
  });

  it('il Toast ha "Chiudi" e segue la lingua', async () => {
    await i18n.changeLanguage('en');
    render(
      <ToastProvider>
        <Toast open>
          <ToastClose />
        </Toast>
        <ToastViewport />
      </ToastProvider>,
    );
    expect(screen.getByRole('button', { name: 'Close' })).toHaveClass(...touch);
  });
});
