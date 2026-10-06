import { describe, it, expect, vi, beforeEach } from 'vitest';
import { authFetch } from '@/api/client';
import type { ReminderResponse } from '@/api/types/reminder';
import {
  completeReminder,
  createReminder,
  deleteReminder,
  getReminder,
  listReminders,
  listUpcomingReminders,
  updateReminder,
} from './reminders';

vi.mock('@/api/client', () => ({ authFetch: vi.fn() }));
const mockedAuthFetch = vi.mocked(authFetch);

const response: ReminderResponse = {
  id: 9,
  vehicle: { id: 3, name: 'Panda', brand: 'Fiat', model: 'Panda' },
  type: 'inspection',
  description: 'Revisione',
  dueDate: '2026-12-31T00:00:00+00:00',
  dueKm: null,
  notifyDaysBefore: 30,
  completedAt: null,
};

const dto = { vehicleId: 3, type: 'inspection', description: 'Revisione', dueDate: '2026-12-31', notifyDaysBefore: 30 } as const;

describe('endpoint promemoria', () => {
  beforeEach(() => mockedAuthFetch.mockReset());

  it('la lista è filtrata per veicolo e restituisce la forma del client (data senza orario, id del veicolo)', async () => {
    mockedAuthFetch.mockResolvedValue([response]);

    const [reminder] = await listReminders(3);

    expect(mockedAuthFetch).toHaveBeenCalledWith('/api/reminders?vehicleId=3');
    expect(reminder).toEqual({
      id: 9,
      vehicleId: 3,
      type: 'inspection',
      description: 'Revisione',
      dueDate: '2026-12-31',
      dueKm: null,
      notifyDaysBefore: 30,
      completedAt: null,
    });
  });

  it('le scadenze in arrivo usano finestra e limite richiesti (default 30 giorni, 5 elementi)', async () => {
    mockedAuthFetch.mockResolvedValue([]);

    await listUpcomingReminders();
    await listUpcomingReminders(90, 10);

    expect(mockedAuthFetch).toHaveBeenNthCalledWith(1, '/api/reminders/upcoming?days=30&limit=5');
    expect(mockedAuthFetch).toHaveBeenNthCalledWith(2, '/api/reminders/upcoming?days=90&limit=10');
  });

  it('un promemoria a soli chilometri resta senza data', async () => {
    mockedAuthFetch.mockResolvedValue({ ...response, dueDate: null, dueKm: 120000 });

    await expect(getReminder(9)).resolves.toMatchObject({ dueDate: null, dueKm: 120000 });
    expect(mockedAuthFetch).toHaveBeenCalledWith('/api/reminders/9');
  });

  it('create e update mandano JSON con il verbo giusto', async () => {
    mockedAuthFetch.mockResolvedValue(response);

    await createReminder(dto);
    await updateReminder(9, dto);

    const [createPath, createInit] = mockedAuthFetch.mock.calls[0] ?? [];
    const [updatePath, updateInit] = mockedAuthFetch.mock.calls[1] ?? [];
    expect([createPath, createInit?.method]).toEqual(['/api/reminders', 'POST']);
    expect([updatePath, updateInit?.method]).toEqual(['/api/reminders/9', 'PUT']);
    for (const init of [createInit, updateInit]) {
      expect(init?.headers).toEqual({ 'Content-Type': 'application/json' });
      expect(JSON.parse(String(init?.body))).toEqual(dto);
    }
  });

  it('complete è un POST senza corpo; delete è un DELETE', async () => {
    mockedAuthFetch.mockResolvedValue({ ...response, completedAt: '2026-10-05T10:00:00+00:00' });

    const done = await completeReminder(9);
    await deleteReminder(9);

    expect(mockedAuthFetch).toHaveBeenNthCalledWith(1, '/api/reminders/9/complete', { method: 'POST' });
    expect(done.completedAt).toBe('2026-10-05T10:00:00+00:00');
    expect(mockedAuthFetch).toHaveBeenNthCalledWith(2, '/api/reminders/9', { method: 'DELETE' });
  });

  it('una risposta senza veicolo (contratto rotto) fallisce invece di produrre un promemoria orfano', async () => {
    mockedAuthFetch.mockResolvedValue({ ...response, vehicle: undefined });

    await expect(getReminder(9)).rejects.toThrow();
  });
});
