import { useTranslation } from 'react-i18next';
import { Heading, Text } from '@/components/ui';

/**
 * AuthBrand feature — header brand riusato in login/register/forgot-password.
 *
 * @example
 * <AuthBrand subtitleKey="auth.login.title" />
 */
export interface AuthBrandProps {
  /** Chiave i18n del sottotitolo (es. 'auth.login.title') */
  subtitleKey: string;
}

export function AuthBrand({ subtitleKey }: AuthBrandProps) {
  const { t } = useTranslation();
  return (
    <div className="space-y-2 text-center">
      <Heading level={1}>{t('app.name')}</Heading>
      <Text variant="muted">{t(subtitleKey)}</Text>
    </div>
  );
}
