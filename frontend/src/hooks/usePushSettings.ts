import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { authFetch } from '@/api/client';

const JSON_HEADERS = { 'Content-Type': 'application/json' };

export interface PushSettings {
  /** Public VAPID key (non segreta). '' se non ancora generata. */
  publicKey: string;
  subject: string;
  enabled: boolean;
  hasKeys: boolean;
  /** Sorgente DAVVERO in uso per l'invio: può differire da 'db' se hai generato ma non abilitato. */
  source: 'db' | 'none';
}

export interface SavePushSettingsPayload {
  subject?: string;
}

const pushSettingsKey = ['settings', 'push'] as const;

/**
 * Query key condivisa con {@see usePushSubscription}: generare/salvare le chiavi
 * qui deve invalidare la public key letta lato "Notifiche" (altrimenti resta
 * su 'not-configured' finché l'utente non ricarica la pagina).
 */
export const vapidPublicKeyKey = ['push', 'vapid-public-key'] as const;

/** Config Web Push (VAPID) di istanza (solo owner: il backend risponde 403 agli altri). */
export function usePushSettings(enabled: boolean) {
  return useQuery({
    queryKey: pushSettingsKey,
    queryFn: () => authFetch<PushSettings>('/api/settings/push'),
    enabled,
  });
}

export function useSavePushSettings() {
  const qc = useQueryClient();
  return useMutation({
    // `enabled` non è più editabile dall'UI: generare una coppia VAPID attiva
    // già da sola (PushSettingsController::generate), qui si aggiorna solo
    // il subject. Il backend richiede comunque il campo, quindi true fisso.
    mutationFn: (payload: SavePushSettingsPayload) =>
      authFetch<PushSettings>('/api/settings/push', {
        method: 'PUT',
        headers: JSON_HEADERS,
        body: JSON.stringify({ ...payload, enabled: true }),
      }),
    onSuccess: (data) => {
      qc.setQueryData(pushSettingsKey, data);
      void qc.invalidateQueries({ queryKey: vapidPublicKeyKey });
    },
  });
}

/** Genera una nuova coppia VAPID. Invalida e cancella tutte le subscription esistenti. */
export function useGenerateVapidKeys() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () =>
      authFetch<{ publicKey: string; removedSubscriptions: number }>('/api/settings/push/generate', {
        method: 'POST',
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: pushSettingsKey });
      void qc.invalidateQueries({ queryKey: vapidPublicKeyKey });
    },
  });
}

/** Invia una notifica push di prova ai device web dell'utente corrente. */
export function useTestPush() {
  return useMutation({
    mutationFn: () => authFetch<{ sent: number }>('/api/settings/push/test', { method: 'POST' }),
  });
}
