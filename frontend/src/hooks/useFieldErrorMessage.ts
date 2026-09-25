import { useTranslation } from 'react-i18next';

/**
 * useFieldErrorMessage — traduce le chiavi errore dei FormField.
 * La chiave può arrivare da Zod (`account.email.invalid`) o dal backend
 * (`auth.email_taken`): prova `errors.<k>`, poi `<k>`, poi la chiave raw.
 */
export function useFieldErrorMessage() {
  const { t } = useTranslation();
  return (k?: string) => (k ? t(`errors.${k}`, { defaultValue: t(k, { defaultValue: k }) }) : undefined);
}
