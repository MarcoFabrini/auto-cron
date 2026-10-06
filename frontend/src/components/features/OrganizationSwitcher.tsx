import { useTranslation } from 'react-i18next';
import { Check, ChevronsUpDown } from 'lucide-react';
import {
  Badge,
  Button,
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
  Heading,
  Spinner,
} from '@/components/ui';
import { useActiveMembership } from '@/hooks/useActiveMembership';
import { useSwitchOrganization } from '@/hooks/useSwitchOrganization';
import { useAuthStore } from '@/stores/useAuthStore';
import { cn } from '@/lib/utils';

export interface OrganizationSwitcherProps {
  /** Tag del nome quando c'è una sola organizzazione (mai un heading: precederebbe l'h1 della pagina). */
  as?: 'p' | 'span';
  className?: string;
}

/**
 * Nome dell'organizzazione attiva (Sidebar desktop, Topbar mobile). Con più di una membership
 * accettata diventa il trigger di un menu per passare a un'altra; con una sola resta testo, senza
 * bottone. /me elenca solo membership accettate, quindi non serve filtrare gli inviti.
 */
export function OrganizationSwitcher({ as = 'span', className }: OrganizationSwitcherProps) {
  const { t } = useTranslation();
  const memberships = useAuthStore((s) => s.user?.memberships);
  const active = useActiveMembership();
  const { switchTo, isPending } = useSwitchOrganization();
  const name = active?.organization.name?.trim() || t('app.name');

  if (!memberships || memberships.length < 2) {
    return (
      <Heading level={4} as={as} className={cn('min-w-0 truncate', className)}>
        {name}
      </Heading>
    );
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          aria-label={t('org.switch.trigger', { name })}
          aria-busy={isPending}
          className={cn('min-w-0 max-w-full justify-between gap-2 px-2 text-base font-semibold md:text-lg', className)}
        >
          <span className="min-w-0 truncate">{name}</span>
          {isPending ? <Spinner size="sm" /> : <ChevronsUpDown aria-hidden />}
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="start" className="w-64 max-w-[calc(100vw-2rem)]">
        <DropdownMenuLabel>{t('org.switch.label')}</DropdownMenuLabel>
        <DropdownMenuSeparator />
        {memberships.map((m) => {
          const isActive = m.organization.id === active?.organization.id;
          return (
            <DropdownMenuItem
              key={m.id}
              aria-current={isActive ? 'true' : undefined}
              // L'organizzazione attiva è inerte: resta raggiungibile da tastiera/screen reader (ha
              // il suo stato nel nome) ma sceglierla non fa nulla.
              onSelect={() => {
                if (!isActive) switchTo(m.organization.id);
              }}
            >
              <Check aria-hidden className={cn('size-4 shrink-0 text-primary', !isActive && 'invisible')} />
              <span className="min-w-0 flex-1 truncate">{m.organization.name}</span>
              <Badge variant="secondary">{t(`settings.role.${m.role}`)}</Badge>
              {isActive && <span className="sr-only">{t('org.switch.current')}</span>}
            </DropdownMenuItem>
          );
        })}
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
