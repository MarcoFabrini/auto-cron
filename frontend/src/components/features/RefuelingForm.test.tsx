import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import i18n from '@/i18n';
import { ApiError } from '@/api/client';
import { RefuelingForm } from './RefuelingForm';

function renderForm(props: Partial<React.ComponentProps<typeof RefuelingForm>> = {}) {
  const onSubmit = vi.fn();
  const utils = render(<RefuelingForm vehicleId={7} onSubmit={onSubmit} {...props} />);
  return { onSubmit, ...utils };
}

describe('RefuelingForm', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it');
  });

  it('accetta la virgola decimale della tastiera italiana e invia col punto', async () => {
    const user = userEvent.setup();
    const { onSubmit } = renderForm();

    await user.clear(screen.getByLabelText(/Km/));
    await user.type(screen.getByLabelText(/Km/), '45000');
    await user.type(screen.getByLabelText(/Litri/), '42,5');
    await user.type(screen.getByLabelText(/€\/L/), '1,859');
    await user.click(screen.getByRole('button', { name: 'Salva' }));

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1));
    expect(onSubmit.mock.calls[0]?.[0]).toMatchObject({ liters: '42.5', pricePerLiter: '1.859', km: 45000 });
  });

  it('il totale in anteprima si calcola anche con la virgola', async () => {
    const user = userEvent.setup();
    renderForm();

    await user.type(screen.getByLabelText(/Litri/), '40,0');
    await user.type(screen.getByLabelText(/€\/L/), '1,5');

    expect(await screen.findByText(/60,00/)).toBeInTheDocument();
  });

  it('ha il campo Note e lo invia (vuoto → null)', async () => {
    const user = userEvent.setup();
    const { onSubmit } = renderForm();

    await user.type(screen.getByLabelText(/Litri/), '30');
    await user.type(screen.getByLabelText(/€\/L/), '1,8');
    await user.type(screen.getByLabelText(/Note/), 'Distributore in autostrada');
    await user.click(screen.getByRole('button', { name: 'Salva' }));

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1));
    expect(onSubmit.mock.calls[0]?.[0]).toMatchObject({ notes: 'Distributore in autostrada' });
  });

  it('testo non numerico: errore sul campo, nessun invio', async () => {
    const user = userEvent.setup();
    const { onSubmit } = renderForm();

    await user.type(screen.getByLabelText(/Litri/), 'abc');
    await user.type(screen.getByLabelText(/€\/L/), '1,8');
    await user.click(screen.getByRole('button', { name: 'Salva' }));

    expect(await screen.findByText(/numero/i)).toBeInTheDocument();
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it('gli errori di validazione del backend compaiono sul campo giusto', async () => {
    const error = new ApiError('validation_failed', 422, undefined, [
      { field: 'station', message: 'Nome distributore troppo lungo' },
    ]);
    renderForm({ error });

    expect(await screen.findByText('Nome distributore troppo lungo')).toBeInTheDocument();
  });

  it('precompila il km con defaultKm invece che con 0', () => {
    renderForm({ defaultKm: 45230 });

    expect(screen.getByLabelText(/Km/)).toHaveValue(45230);
  });

  it('aggiorna il km precompilato quando defaultKm arriva dopo il mount (query async)', async () => {
    const onSubmit = vi.fn();
    const { rerender } = render(
      <RefuelingForm vehicleId={7} onSubmit={onSubmit} />,
    );

    expect(screen.getByLabelText(/Km/)).toHaveValue(0);

    rerender(<RefuelingForm vehicleId={7} onSubmit={onSubmit} defaultKm={45230} />);

    await waitFor(() => expect(screen.getByLabelText(/Km/)).toHaveValue(45230));
  });

  it('non sovrascrive il km se l\'utente lo ha già modificato prima che defaultKm arrivi', async () => {
    const onSubmit = vi.fn();
    const user = userEvent.setup();
    const { rerender } = render(
      <RefuelingForm vehicleId={7} onSubmit={onSubmit} />,
    );

    await user.clear(screen.getByLabelText(/Km/));
    await user.type(screen.getByLabelText(/Km/), '12000');

    rerender(<RefuelingForm vehicleId={7} onSubmit={onSubmit} defaultKm={45230} />);

    expect(screen.getByLabelText(/Km/)).toHaveValue(12000);
  });

  it('in modifica, il km del rifornimento esistente ha priorità su defaultKm', () => {
    renderForm({ defaultKm: 45230, defaultValues: { km: 30000 } });

    expect(screen.getByLabelText(/Km/)).toHaveValue(30000);
  });
});
