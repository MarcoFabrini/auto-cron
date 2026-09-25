import { authFetch } from '@/api/client';
import {
  adaptExpense,
  type CreateExpenseDto,
  type Expense,
  type ExpenseResponse,
  type UpdateExpenseDto,
} from '@/api/types/expense';

export async function listExpenses(vehicleId: number): Promise<Expense[]> {
  const raw = await authFetch<ExpenseResponse[]>(`/api/expenses?vehicleId=${vehicleId}`);
  return raw.map(adaptExpense);
}

export async function getExpense(id: number): Promise<Expense> {
  const raw = await authFetch<ExpenseResponse>(`/api/expenses/${id}`);
  return adaptExpense(raw);
}

export async function createExpense(body: CreateExpenseDto): Promise<Expense> {
  const raw = await authFetch<ExpenseResponse>('/api/expenses', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return adaptExpense(raw);
}

export async function updateExpense(id: number, body: UpdateExpenseDto): Promise<Expense> {
  const raw = await authFetch<ExpenseResponse>(`/api/expenses/${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  return adaptExpense(raw);
}

export function deleteExpense(id: number) {
  return authFetch<void>(`/api/expenses/${id}`, { method: 'DELETE' });
}
