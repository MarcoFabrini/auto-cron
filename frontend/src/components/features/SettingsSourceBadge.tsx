import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui';

/**
 * SettingsSourceBadge feature — mostra da dove viene la config Push VAPID
 * davvero usata per l'invio: DB (questa interfaccia, unica sorgente
 * possibile) o nessuna. Usato da PushSettingsCard (SMTP è solo env, niente UI).
 *
 * @example
 * <SettingsSourceBadge source="db" />
 */
export interface SettingsSourceBadgeProps {
  source: 'db' | 'none';
}

const variantBySource: Record<SettingsSourceBadgeProps['source'], 'success' | 'outline'> = {
  db: 'success',
  none: 'outline',
};

export function SettingsSourceBadge({ source }: SettingsSourceBadgeProps) {
  const { t } = useTranslation();
  return <Badge variant={variantBySource[source]}>{t(`settings.source_${source}`)}</Badge>;
}
