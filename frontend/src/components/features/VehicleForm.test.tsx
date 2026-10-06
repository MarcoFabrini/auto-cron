import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ApiError } from '@/api/client';
import i18n from '@/i18n';
import { VEHICLE_NOTES_MAX_LENGTH, vehicleSchema } from '@/schemas/vehicle.schema';
import { VehicleForm } from './VehicleForm';

describe('VehicleForm — note', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it');
  });

  it('il limite del campo è quello reale (5000, come schema e backend), non 2000', () => {
    render(<VehicleForm onSubmit={vi.fn()} />);

    expect(VEHICLE_NOTES_MAX_LENGTH).toBe(5000);
    expect(screen.getByLabelText(/Note/)).toHaveAttribute('maxlength', String(VEHICLE_NOTES_MAX_LENGTH));
  });

  it('lo schema accetta note fino al limite e le rifiuta oltre', () => {
    const base = {
      name: 'Golf',
      brand: 'VW',
      model: 'Golf',
      year: 2020,
      type: 'car',
      fuelType: 'diesel',
      initialKm: 0,
    };

    expect(vehicleSchema.safeParse({ ...base, notes: 'a'.repeat(VEHICLE_NOTES_MAX_LENGTH) }).success).toBe(true);
    expect(vehicleSchema.safeParse({ ...base, notes: 'a'.repeat(VEHICLE_NOTES_MAX_LENGTH + 1) }).success).toBe(false);
  });

  it('invia note più lunghe di 2000 caratteri', async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<VehicleForm defaultValues={{ name: 'Golf', brand: 'VW', model: 'Golf', notes: 'x'.repeat(3000) }} onSubmit={onSubmit} />);

    await user.click(screen.getByRole('button', { name: 'Salva' }));

    await waitFor(() => expect(onSubmit).toHaveBeenCalledTimes(1));
    expect(onSubmit.mock.calls[0]?.[0]).toMatchObject({ notes: 'x'.repeat(3000) });
  });
});

describe('VehicleForm — errori del server', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it');
  });

  it('mostra il messaggio tradotto quando l\'organizzazione ha raggiunto il limite di veicoli', () => {
    render(<VehicleForm onSubmit={vi.fn()} error={new ApiError('vehicle.limit_reached', 422)} />);

    expect(
      screen.getByText('Hai raggiunto il numero massimo di veicoli consentito per questa organizzazione'),
    ).toBeInTheDocument();
  });
});
