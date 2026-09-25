import { authFetch } from '@/api/client';
import {
  adaptRefueling,
  type CreateRefuelingDto,
  type Refueling,
  type RefuelingResponse,
  type UpdateRefuelingDto,
} from '@/api/types/refueling';

export async function listRefuelings(vehicleId: number): Promise<Refueling[]> {
  const raw = await authFetch<RefuelingResponse[]>(`/api/refuelings?vehicleId=${vehicleId}`);
  return raw.map(adaptRefueling);
}

export async function getRefueling(id: number): Promise<Refueling> {
  const raw = await authFetch<RefuelingResponse>(`/api/refuelings/${id}`);
  return adaptRefueling(raw);
}

export async function createRefueling(body: CreateRefuelingDto): Promise<Refueling> {
  const raw = await authFetch<RefuelingResponse>('/api/refuelings', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return adaptRefueling(raw);
}

export async function updateRefueling(id: number, body: UpdateRefuelingDto): Promise<Refueling> {
  const raw = await authFetch<RefuelingResponse>(`/api/refuelings/${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return adaptRefueling(raw);
}

export function deleteRefueling(id: number) {
  return authFetch<void>(`/api/refuelings/${id}`, { method: 'DELETE' });
}
