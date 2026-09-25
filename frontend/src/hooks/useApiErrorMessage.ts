import { useTranslation } from 'react-i18next';
import { ApiError } from '@/api/client';

/**
 * useApiErrorMessage — mappa ApiError a stringa user-friendly i18n.
 *
 * Convention: backend ritorna `title` = chiave i18n stabile
 * (es. `auth.invalid_credentials`). Hook prefixa con `errors.` e
 * fa fallback a `errors.unknown` se chiave mancante.
 */
export function useApiErrorMessage() {
  const { t } = useTranslation();

  return (error: unknown): string => {
    if (error instanceof ApiError) {
      return t(`errors.${error.title}`, {
        defaultValue: error.detail ?? t('errors.unknown'),
      });
    }
    if (error instanceof Error) return error.message;
    return t('errors.unknown');
  };
}
