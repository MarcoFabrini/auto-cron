/** Quanti tentativi in più dopo il primo errore (rete o 5xx): 2 retry, poi l'errore arriva alla UI. */
export const MAX_QUERY_RETRIES = 2;

function statusOf(error: unknown): number | undefined {
  if (typeof error !== 'object' || error === null || !('status' in error)) return undefined;
  return typeof error.status === 'number' ? error.status : undefined;
}

/**
 * Policy `retry` del QueryClient globale. Un 4xx (400, 404, 409, 422…) è la risposta definitiva del
 * server: ripeterlo non cambia l'esito e tiene lo scheletro a schermo per ~3 s prima dell'errore.
 * Si riprova solo ciò che può essere transitorio: errori di rete, errori senza status e 5xx.
 */
export function shouldRetryQuery(failureCount: number, error: unknown): boolean {
  const status = statusOf(error);
  if (status !== undefined && status >= 400 && status < 500) return false;
  return failureCount < MAX_QUERY_RETRIES;
}
