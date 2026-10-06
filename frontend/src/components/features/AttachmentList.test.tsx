import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { authFetch } from '@/api/client';
import type { AttachmentResponse } from '@/api/types/attachment';
import { AttachmentList } from './AttachmentList';

vi.mock('@/api/client', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/client')>()),
  authFetch: vi.fn(),
}));
const mockedAuthFetch = vi.mocked(authFetch);

const pdf: AttachmentResponse = {
  id: 9,
  entityType: 'maintenance',
  entityId: '4',
  originalFilename: 'fattura.pdf',
  mimeType: 'application/pdf',
  sizeBytes: 2048,
  createdAt: '2026-09-01T10:00:00+00:00',
};

function renderList(readOnly = false) {
  mockedAuthFetch.mockResolvedValue([pdf]);
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <AttachmentList entityType="maintenance" entityId={4} readOnly={readOnly} />
    </QueryClientProvider>,
  );
}

describe('AttachmentList', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
  });

  it('il pulsante elimina raggiunge i 44px (jsdom non ha layout: si verifica la classe)', async () => {
    renderList();

    const del = await screen.findByRole('button', { name: 'Elimina' });
    expect(del).toHaveClass('min-h-touch', 'min-w-touch');
  });

  it('in sola lettura non c\'è il pulsante elimina', async () => {
    renderList(true);

    await screen.findByText('fattura.pdf');
    expect(screen.queryByRole('button', { name: 'Elimina' })).not.toBeInTheDocument();
  });
});
