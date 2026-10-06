import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import i18n from '@/i18n';
import { ConfirmDialog } from './ConfirmDialog';

describe('ConfirmDialog', () => {
  afterEach(() => vi.restoreAllMocks());

  it.each([
    ['senza descrizione', undefined],
    ['con descrizione', 'Operazione irreversibile.'],
  ])('%s si apre senza warning Radix sulla descrizione', async (_case, description) => {
    await i18n.changeLanguage('it');
    const error = vi.spyOn(console, 'error').mockImplementation(() => {});
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});

    render(<ConfirmDialog open onOpenChange={() => {}} title="Eliminare?" description={description} onConfirm={() => {}} />);

    expect(screen.getByRole('dialog', { name: 'Eliminare?' })).toBeInTheDocument();
    const logged = [...error.mock.calls, ...warn.mock.calls].flat().map(String);
    expect(logged.filter((m) => /Description|aria-describedby/.test(m))).toEqual([]);
  });
});
