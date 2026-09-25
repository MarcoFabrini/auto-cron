import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import i18n from '@/i18n';
import { MaintenanceForm } from './MaintenanceForm';

function renderForm(props: Partial<React.ComponentProps<typeof MaintenanceForm>> = {}) {
  const onSubmit = vi.fn();
  const utils = render(<MaintenanceForm vehicleId={7} onSubmit={onSubmit} {...props} />);
  return { onSubmit, ...utils };
}

describe('MaintenanceForm', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it');
  });

  it('precompila il km con defaultKm invece che con 0', () => {
    renderForm({ defaultKm: 45230 });

    expect(screen.getByLabelText(/Km/)).toHaveValue(45230);
  });

  it('aggiorna il km precompilato quando defaultKm arriva dopo il mount (query async)', async () => {
    const onSubmit = vi.fn();
    const { rerender } = render(<MaintenanceForm vehicleId={7} onSubmit={onSubmit} />);

    expect(screen.getByLabelText(/Km/)).toHaveValue(0);

    rerender(<MaintenanceForm vehicleId={7} onSubmit={onSubmit} defaultKm={45230} />);

    await waitFor(() => expect(screen.getByLabelText(/Km/)).toHaveValue(45230));
  });

  it("non sovrascrive il km se l'utente lo ha già modificato prima che defaultKm arrivi", async () => {
    const onSubmit = vi.fn();
    const user = userEvent.setup();
    const { rerender } = render(<MaintenanceForm vehicleId={7} onSubmit={onSubmit} />);

    await user.clear(screen.getByLabelText(/Km/));
    await user.type(screen.getByLabelText(/Km/), '12000');

    rerender(<MaintenanceForm vehicleId={7} onSubmit={onSubmit} defaultKm={45230} />);

    expect(screen.getByLabelText(/Km/)).toHaveValue(12000);
  });

  it('in modifica, il km della manutenzione esistente ha priorità su defaultKm', () => {
    renderForm({ defaultKm: 45230, defaultValues: { km: 30000 } });

    expect(screen.getByLabelText(/Km/)).toHaveValue(30000);
  });
});
