import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ChevronLeft, Plus } from 'lucide-react';
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
import { VEHICLE_RECORD_ACTIONS } from './vehicleRecordActions';
import { useVehicles } from '@/hooks/useVehicles';
import type { Vehicle } from '@/api/types/vehicle';

/**
 * QuickAddSheet — hub centrale di tutte le aggiunte (pulsante "+" della BottomNav).
 * Flusso a 2 step: prima si sceglie il veicolo (o "Nuovo veicolo"), poi cosa
 * aggiungere per quel veicolo. Si sceglie solo fra i veicoli propri: non quelli
 * condivisi in sola lettura né, per owner/admin dell'org, quelli degli altri membri
 * (che si gestiscono dal loro dettaglio). Con un solo veicolo proprio il primo step
 * non ha nulla da scegliere e si salta (il "indietro" lo riapre, per aggiungerne un altro).
 */
export interface QuickAddSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

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

  const list = (vehicles ?? []).filter((v) => v.ownership === 'owned');
  const onlyVehicle = list.length === 1 ? (list[0] ?? null) : null;
  const vehicle = selected ?? onlyVehicle;
  const currentStep = step ?? (onlyVehicle ? 'entity' : 'vehicle');

  return (
    <Sheet open={open} onOpenChange={handleOpenChange}>
      <SheetContent side="bottom" className="max-h-[85vh] overflow-y-auto overscroll-contain">
        {isLoading ? (
          // Finché la lista non arriva non si sa se c'è qualcosa da scegliere: niente step veicolo.
          <>
            <SheetHeader>
              <SheetTitle>{t('quick_add.open')}</SheetTitle>
            </SheetHeader>
            <div className="grid grid-cols-2 gap-3 pt-2">
              {Array.from({ length: 4 }).map((_, i) => (
                <Skeleton key={i} className="h-24 w-full rounded-lg" />
              ))}
            </div>
          </>
        ) : currentStep === 'vehicle' || !vehicle ? (
          <>
            <SheetHeader>
              <SheetTitle>{t('quick_add.choose_vehicle')}</SheetTitle>
            </SheetHeader>
            <div className="space-y-3 pt-2">
              <Button className="w-full" onClick={() => go('/vehicles/new')}>
                <Plus />
                {t('quick_add.new_vehicle')}
              </Button>

              {list.length > 0 && (
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
              {VEHICLE_RECORD_ACTIONS.map(({ path, labelKey, Icon }) => (
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
