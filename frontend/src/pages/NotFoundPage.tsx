import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Home } from 'lucide-react';
import { Button, Heading, Text } from '@/components/ui';

/**
 * NotFoundPage — catch-all 404 dentro AppLayout (utente autenticato).
 * Per utenti non autenticati il route guard redirige a /login.
 */
export function NotFoundPage() {
  const { t } = useTranslation();

  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center gap-4 text-center">
      <p className="text-6xl font-bold text-muted-foreground/40 md:text-7xl">404</p>
      <Heading level={2}>{t('error_page.not_found.title')}</Heading>
      <Text variant="muted" className="max-w-md">
        {t('error_page.not_found.description')}
      </Text>
      <Button asChild className="mt-2">
        <Link to="/">
          <Home />
          {t('error_page.back_home')}
        </Link>
      </Button>
    </div>
  );
}
