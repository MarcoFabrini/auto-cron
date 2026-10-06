import { describe, it, expect, vi, beforeEach } from 'vitest';
import type { ReactElement } from 'react';
import { render, screen, within } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import i18n from '@/i18n';
import { ApiError, authFetch } from '@/api/client';
import type { DashboardCharts as DashboardChartsData } from '@/api/types/dashboardCharts';
import { DashboardCharts } from './DashboardCharts';

vi.mock('@/api/client', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/api/client')>()),
  authFetch: vi.fn(),
}));
const mockedAuthFetch = vi.mocked(authFetch);

// In jsdom ResponsiveContainer misura 0×0 e non disegna nulla: dimensione fissa.
vi.mock('recharts', async (importOriginal) => {
  const { cloneElement } = await import('react');
  return {
    ...(await importOriginal<typeof import('recharts')>()),
    ResponsiveContainer: ({ children }: { children: ReactElement<{ width?: number; height?: number }> }) =>
      cloneElement(children, { width: 400, height: 240 }),
  };
});

const zero = { refuelings: '0.00', maintenances: '0.00', expenses: '0.00', total: '0.00' };

const withData: DashboardChartsData = {
  from: '2026-09',
  to: '2026-10',
  fuelTypes: ['gasoline', 'lpg'],
  months: [
    {
      month: '2026-09',
      spending: { refuelings: '84.58', maintenances: '120.00', expenses: '650.00', total: '854.58' },
      kmDriven: 1200,
      consumption: { gasoline: 14.2, lpg: 10.5 },
    },
    { month: '2026-10', spending: zero, kmDriven: 300, consumption: { gasoline: 15.1, lpg: null } },
  ],
  spendingByCategory: [
    { category: 'insurance', amount: '650.00' },
    { category: 'maintenance', amount: '120.00' },
    { category: 'fuel', amount: '84.58' },
  ],
  totals: { spending: '854.58', kmDriven: 1500 },
};

const empty: DashboardChartsData = {
  from: '2026-09',
  to: '2026-10',
  fuelTypes: ['diesel'],
  months: [
    { month: '2026-09', spending: zero, kmDriven: 0, consumption: { diesel: null } },
    { month: '2026-10', spending: zero, kmDriven: 0, consumption: { diesel: null } },
  ],
  spendingByCategory: [],
  totals: { spending: '0.00', kmDriven: 0 },
};

function renderCharts() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={qc}>
      <DashboardCharts />
    </QueryClientProvider>,
  );
}

describe('DashboardCharts', () => {
  beforeEach(async () => {
    mockedAuthFetch.mockReset();
    await i18n.changeLanguage('it');
  });

  it('chiede 12 mesi e mostra i 4 grafici con titoli e legende tradotti', async () => {
    mockedAuthFetch.mockResolvedValue(withData);
    renderCharts();

    expect(await screen.findByRole('table', { name: 'Spese mensili' })).toBeInTheDocument();
    expect(mockedAuthFetch).toHaveBeenCalledWith('/api/dashboard/charts?months=12');
    // Il titolo della card ricompare come didascalia della tabella accessibile.
    expect(screen.getAllByText('Spese mensili')).toHaveLength(2);
    expect(screen.getAllByText('Spese per categoria')).toHaveLength(2);
    expect(screen.getAllByText('Km percorsi')).toHaveLength(2);
    expect(screen.getAllByText('Consumo medio (km/l)')).toHaveLength(2);
    // Legenda delle spese mensili (ogni nome è anche intestazione della tabella accessibile).
    expect(screen.getAllByText('Rifornimenti')).toHaveLength(2);
    expect(screen.getAllByText('Manutenzioni')).toHaveLength(2);
    expect(screen.getAllByText('Spese')).toHaveLength(2);
    // Categorie tradotte, con importo (legenda visiva + riga della tabella).
    expect(screen.getAllByText('Assicurazione')).toHaveLength(2);
    expect(screen.getAllByText('Manutenzione')).toHaveLength(2);
    expect(screen.getAllByText('Carburante')).toHaveLength(2);
    expect(within(screen.getByRole('table', { name: 'Spese per categoria' })).getByText(/^650,00\s€$/)).toBeInTheDocument();
    // Una linea per carburante, con il nome tradotto (legenda + intestazione).
    expect(screen.getAllByText('Benzina')).toHaveLength(2);
    expect(screen.getAllByText('GPL')).toHaveLength(2);
    expect(screen.queryByText('Nessuna spesa nel periodo.')).not.toBeInTheDocument();
  });

  it('in inglese titoli, serie e categorie sono in inglese', async () => {
    await i18n.changeLanguage('en');
    mockedAuthFetch.mockResolvedValue(withData);
    renderCharts();

    expect(await screen.findAllByText('Monthly spending')).toHaveLength(2);
    const spending = screen.getByRole('table', { name: 'Monthly spending' });
    expect(within(spending).getByRole('columnheader', { name: 'Refuelings' })).toBeInTheDocument();
    expect(within(screen.getByRole('table', { name: 'Spending by category' })).getByRole('rowheader', { name: 'Insurance' })).toBeInTheDocument();
    expect(within(screen.getByRole('table', { name: /^Average consumption/ })).getByRole('columnheader', { name: 'LPG' })).toBeInTheDocument();
  });

  it('più di 5 categorie: le prime 5 e "Altre categorie"', async () => {
    mockedAuthFetch.mockResolvedValue({
      ...withData,
      spendingByCategory: [
        { category: 'insurance', amount: '650.00' },
        { category: 'road_tax', amount: '300.00' },
        { category: 'maintenance', amount: '120.00' },
        { category: 'fuel', amount: '84.58' },
        { category: 'toll', amount: '40.00' },
        { category: 'parking', amount: '12.00' },
        { category: 'fine', amount: '8.00' },
      ],
    });
    renderCharts();

    expect(await screen.findAllByText('Altre categorie')).toHaveLength(2);
    expect(screen.getAllByText('Pedaggio')).toHaveLength(2);
    expect(screen.queryByText('Parcheggio')).not.toBeInTheDocument();
    expect(screen.queryByText('Multa')).not.toBeInTheDocument();
    // 12,00 + 8,00 delle due categorie raggruppate.
    expect(screen.getAllByText(/^20,00\s€$/)).toHaveLength(2);
  });

  it('senza dati ogni grafico mostra il proprio messaggio vuoto', async () => {
    mockedAuthFetch.mockResolvedValue(empty);
    renderCharts();

    expect(await screen.findByText('Nessuna spesa nel periodo.')).toBeInTheDocument();
    expect(screen.getByText('Nessuna spesa da suddividere nel periodo.')).toBeInTheDocument();
    expect(screen.getByText('Nessuna lettura del contachilometri nel periodo.')).toBeInTheDocument();
    expect(screen.getByText('Servono almeno due pieni consecutivi per calcolare il consumo.')).toBeInTheDocument();
    expect(screen.queryByText('Rifornimenti')).not.toBeInTheDocument();
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
  });

  it('payload malformato: Alert di errore, nessun grafico e nessun crash', async () => {
    mockedAuthFetch.mockResolvedValue({ ...withData, totals: null });
    renderCharts();

    expect(await screen.findByText('Dati dei grafici non validi: riprova più tardi.')).toBeInTheDocument();
    expect(screen.queryByText('Spese mensili')).not.toBeInTheDocument();
  });

  it('errore HTTP: Alert con il messaggio tradotto dell\'API', async () => {
    mockedAuthFetch.mockRejectedValue(new ApiError('auth.no_active_organization', 403));
    renderCharts();

    expect(await screen.findByRole('alert')).toBeInTheDocument();
    expect(screen.queryByText('Spese mensili')).not.toBeInTheDocument();
  });
});
