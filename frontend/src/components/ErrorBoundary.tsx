import { Component, type ErrorInfo, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { AlertTriangle } from 'lucide-react';
import { Button, Heading, Text } from '@/components/ui';

interface ErrorBoundaryProps {
  children: ReactNode;
}

interface ErrorBoundaryState {
  error: Error | null;
}

/**
 * ErrorBoundary — unica eccezione alla regola "solo componenti funzionali":
 * React richiede una class per intercettare errori di render. Mostra una
 * fallback UI invece di una schermata bianca quando un componente lancia.
 */
export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  state: ErrorBoundaryState = { error: null };

  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { error };
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    // Log per debugging; in prod un hook futuro potrà inviarlo a un servizio.
    console.error('ErrorBoundary caught an error:', error, info.componentStack);
  }

  private reset = () => this.setState({ error: null });

  render() {
    if (this.state.error) {
      return <ErrorFallback onReset={this.reset} />;
    }
    return this.props.children;
  }
}

/** Fallback funzionale: separato dalla class per poter usare gli hook (i18n). */
function ErrorFallback({ onReset }: { onReset: () => void }) {
  const { t } = useTranslation();

  return (
    <div className="flex min-h-dvh flex-col items-center justify-center gap-4 p-6 text-center">
      <div className="rounded-full bg-destructive/10 p-3">
        <AlertTriangle className="size-7 text-destructive" />
      </div>
      <Heading level={2}>{t('error_page.crash.title')}</Heading>
      <Text variant="muted" className="max-w-md">
        {t('error_page.crash.description')}
      </Text>
      <Button
        className="mt-2"
        onClick={() => {
          onReset();
          window.location.reload();
        }}
      >
        {t('error_page.crash.reload')}
      </Button>
    </div>
  );
}
