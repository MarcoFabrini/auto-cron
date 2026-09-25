import { useEffect } from 'react';
import { Controller, useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import {
  Alert,
  Button,
  Checkbox,
  FormField,
  Input,
  Label,
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
  Spinner,
  Textarea,
  Text,
} from '@/components/ui';
import { FUEL_TYPES } from '@/api/types/vehicle';
import type { Refueling } from '@/api/types/refueling';
import { refuelingSchema, type RefuelingFormData } from '@/schemas/refueling.schema';
import { ApiError } from '@/api/client';
import { useServerFieldErrors } from '@/hooks/useServerFieldErrors';
import { formatCurrency, todayIso } from '@/lib/format';
import { parseDecimal } from '@/lib/decimal';

export interface RefuelingFormProps {
  vehicleId: number;
  /** Default fuelType da Vehicle, usato per pre-compilare */
  defaultFuelType?: RefuelingFormData['fuelType'];
  /** Ultimo km noto del veicolo (da stats), usato per pre-compilare il campo km su un nuovo rifornimento */
  defaultKm?: number;
  defaultValues?: Partial<Refueling>;
  isPending?: boolean;
  error?: unknown;
  onSubmit: (data: RefuelingFormData) => void;
  submitLabel?: string;
  onCancel?: () => void;
}

export function RefuelingForm({
  vehicleId,
  defaultFuelType = 'gasoline',
  defaultKm,
  defaultValues,
  isPending,
  error,
  onSubmit,
  submitLabel,
  onCancel,
}: RefuelingFormProps) {
  const { t } = useTranslation();

  const form = useForm<RefuelingFormData>({
    resolver: zodResolver(refuelingSchema),
    defaultValues: {
      vehicleId,
      refueledAt: defaultValues?.refueledAt ?? todayIso(),
      km: defaultValues?.km ?? defaultKm ?? 0,
      liters: defaultValues?.liters ?? '',
      pricePerLiter: defaultValues?.pricePerLiter ?? '',
      fuelType: defaultValues?.fuelType ?? defaultFuelType,
      fullTank: defaultValues?.fullTank ?? true,
      station: defaultValues?.station ?? null,
      notes: defaultValues?.notes ?? null,
    },
  });

  useServerFieldErrors(form, error);

  // dirtyFields è dietro un Proxy: va letto in fase di render per attivare
  // la subscription, altrimenti nell'effect sotto risulta sempre stale.
  const { dirtyFields } = form.formState;

  // defaultKm arriva async (query stats separata dal mount del form): se il
  // campo km non è ancora stato toccato dall'utente e non c'è un defaultValues
  // esplicito (edit), lo precompiliamo appena disponibile.
  useEffect(() => {
    if (defaultKm !== undefined && defaultValues?.km === undefined && !dirtyFields.km) {
      form.setValue('km', defaultKm);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [defaultKm, dirtyFields.km]);

  // Computed total cost (client-side preview; backend ricalcola)
  const liters = useWatch({ control: form.control, name: 'liters' });
  const pricePerLiter = useWatch({ control: form.control, name: 'pricePerLiter' });
  const litersNum = parseDecimal(liters ?? '');
  const priceNum = parseDecimal(pricePerLiter ?? '');
  const computedTotal =
    Number.isNaN(litersNum) || Number.isNaN(priceNum) ? null : (litersNum * priceNum).toFixed(2);

  // Reset vehicleId if changes (rare edge — controlled prop drift)
  useEffect(() => {
    form.setValue('vehicleId', vehicleId);
  }, [vehicleId, form]);

  const errors = form.formState.errors;
  const tErr = (key: string | undefined) =>
    key ? t(`errors.${key}`, { defaultValue: key }) : undefined;

  return (
    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
      <input type="hidden" {...form.register('vehicleId', { valueAsNumber: true })} />

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <FormField label={t('refueling.refueled_at')} error={tErr(errors.refueledAt?.message)} required>
          {(id) => <Input id={id} type="date" invalid={!!errors.refueledAt} {...form.register('refueledAt')} />}
        </FormField>
        <FormField label={t('refueling.km')} error={tErr(errors.km?.message)} required>
          {(id) => (
            <Input
              id={id}
              type="number"
              inputMode="numeric"
              invalid={!!errors.km}
              {...form.register('km', { valueAsNumber: true })}
            />
          )}
        </FormField>
      </div>

      <FormField label={t('refueling.fuel_type')} error={tErr(errors.fuelType?.message)} required>
        {(id) => (
          <Controller
            control={form.control}
            name="fuelType"
            render={({ field }) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id={id}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {FUEL_TYPES.map((f) => (
                    <SelectItem key={f} value={f}>
                      {t(`vehicle.fuel.${f}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        )}
      </FormField>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <FormField label={t('refueling.liters')} error={tErr(errors.liters?.message)} required>
          {(id) => (
            <Input
              id={id}
              type="text"
              inputMode="decimal"
              placeholder="0.00"
              invalid={!!errors.liters}
              {...form.register('liters')}
            />
          )}
        </FormField>
        <FormField
          label={t('refueling.price_per_liter')}
          error={tErr(errors.pricePerLiter?.message)}
          required
        >
          {(id) => (
            <Input
              id={id}
              type="text"
              inputMode="decimal"
              placeholder="0.000"
              invalid={!!errors.pricePerLiter}
              {...form.register('pricePerLiter')}
            />
          )}
        </FormField>
      </div>

      {computedTotal && (
        <Text variant="muted" className="text-right">
          {t('refueling.total_cost')}: <span className="font-semibold text-foreground">{formatCurrency(computedTotal)}</span>
        </Text>
      )}

      <FormField label={t('refueling.station')} error={tErr(errors.station?.message)} hint={t('common.optional')}>
        {(id) => (
          <Input
            id={id}
            invalid={!!errors.station}
            {...form.register('station', { setValueAs: (v) => (v === '' ? null : v) })}
          />
        )}
      </FormField>

      <label className="flex items-center gap-3">
        <Controller
          control={form.control}
          name="fullTank"
          render={({ field }) => (
            <Checkbox
              checked={field.value}
              onChange={(e) => field.onChange(e.target.checked)}
            />
          )}
        />
        <Label className="cursor-pointer">{t('refueling.full_tank')}</Label>
      </label>

      <FormField label={t('refueling.notes')} error={tErr(errors.notes?.message)} hint={t('common.optional')}>
        {(id) => (
          <Textarea
            id={id}
            maxLength={2000}
            invalid={!!errors.notes}
            {...form.register('notes', { setValueAs: (v) => (v === '' ? null : v) })}
          />
        )}
      </FormField>

      {error instanceof ApiError && error.title !== 'validation_failed' && (
        <Alert variant="error">
          {t(`errors.${error.title}`, { defaultValue: error.detail ?? error.title })}
        </Alert>
      )}

      <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        {onCancel && (
          <Button type="button" variant="outline" onClick={onCancel} disabled={isPending}>
            {t('actions.cancel')}
          </Button>
        )}
        <Button type="submit" disabled={isPending}>
          {isPending ? <Spinner size="sm" /> : (submitLabel ?? t('actions.save'))}
        </Button>
      </div>
    </form>
  );
}
