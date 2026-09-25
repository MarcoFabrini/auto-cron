import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Bell, ChevronLeft, Fuel, Plus, Receipt, Wrench, type LucideIcon } from 'lucide-react';
import {
  Button,
  Card,
  CardContent,
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  Skeleton,
} from '@/components/ui';
import { VehiclePicker } from './VehiclePicker';
import { useVehicles } from '@/hooks/useVehicles';
import type { Vehicle } from '@/api/types/vehicle';

/**
 * QuickAddSheet — hub centrale di tutte le aggiunte (pulsante "+" della BottomNav).
 * Flusso a 2 step: prima si sceglie il veicolo (o "Nuovo veicolo"), poi cosa
 * aggiungere per quel veicolo. Con un solo veicolo il primo step non ha nulla da
 * scegliere e si salta (il "indietro" lo riapre, per aggiungerne un altro).
 * Sostituisce i singoli pulsanti "+" sparsi nelle tab.
 */
export interface QuickAddSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/** Azioni del secondo step: rotta `/new` + label i18n + icona. */
const ENTITY_ACTIONS: { path: string; labelKey: string; Icon: LucideIcon }[] = [
  { path: '/maintenance/new', labelKey: 'maintenance.new', Icon: Wrench },
  { path: '/refueling/new', labelKey: 'refueling.new', Icon: Fuel },
  { path: '/expenses/new', labelKey: 'expense.new', Icon: Receipt },
  { path: '/reminders/new', labelKey: 'reminder.new', Icon: Bell },
];

export function QuickAddSheet({ open, onOpenChange }: QuickAddSheetProps) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data: vehicles, isLoading } = useVehicles();

  // `null` = automatico: step veicolo, salvo un solo veicolo (nulla da scegliere).
  const [step, setStep] = useState<'vehicle' | 'entity' | null>(null);
  const [selected, setSelected] = useState<Vehicle | null>(null);

  // Reset stato a ogni chiusura per ripartire sempre dal punto di partenza automatico.
  function handleOpenChange(next: boolean) {
    if (!next) {
      setStep(null);
      setSelected(null);
    }
    onOpenChange(next);
  }

  function go(to: string) {
    handleOpenChange(false);
    navigate(to);
  }

  const list = vehicles ?? [];
  const onlyVehicle = list.length === 1 ? (list[0] ?? null) : null;
  const vehicle = selected ?? onlyVehicle;
  const currentStep = step ?? (onlyVehicle ? 'entity' : 'vehicle');

  return (
    <Sheet open={open} onOpenChange={handleOpenChange}>
      <SheetContent side="bottom" className="max-h-[85vh] overflow-y-auto overscroll-contain">
        {currentStep === 'vehicle' || !vehicle ? (
          <>
            <SheetHeader>
              <SheetTitle>{t('quick_add.choose_vehicle')}</SheetTitle>
            </SheetHeader>
            <div className="space-y-3 pt-2">
              <Button className="w-full" onClick={() => go('/vehicles/new')}>
                <Plus />
                {t('quick_add.new_vehicle')}
              </Button>

              {isLoading && (
                <div className="grid gap-3 sm:grid-cols-2">
                  {Array.from({ length: 2 }).map((_, i) => (
                    <Skeleton key={i} className="h-24 w-full rounded-lg" />
                  ))}
                </div>
              )}

              {!isLoading && list.length > 0 && (
                <VehiclePicker
                  vehicles={list}
                  onSelect={(id) => {
                    setSelected(list.find((v) => v.id === id) ?? null);
                    setStep('entity');
                  }}
                />
              )}
            </div>
          </>
        ) : (
          <>
            <SheetHeader>
              <SheetTitle className="flex items-center gap-2">
                <Button
                  variant="ghost"
                  size="icon"
                  className="-ml-2 shrink-0"
                  onClick={() => setStep('vehicle')}
                  aria-label={t('actions.back')}
                >
                  <ChevronLeft />
                </Button>
                <span className="truncate">{vehicle.name}</span>
              </SheetTitle>
            </SheetHeader>
            <div className="grid grid-cols-2 gap-3 pt-2">
              {ENTITY_ACTIONS.map(({ path, labelKey, Icon }) => (
                <button
                  key={path}
                  type="button"
                  onClick={() => go(`${path}?vehicleId=${vehicle.id}`)}
                  className="rounded-lg text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                  <Card className="h-full transition-colors hover:border-primary hover:bg-accent/40">
                    <CardContent standalone className="flex flex-col items-center gap-2 text-center">
                      <div className="rounded-md bg-primary/10 p-3">
                        <Icon className="size-6 text-primary" />
                      </div>
                      <span className="text-sm font-medium">{t(labelKey)}</span>
                    </CardContent>
                  </Card>
                </button>
              ))}
            </div>
          </>
        )}
      </SheetContent>
    </Sheet>
  );
}
