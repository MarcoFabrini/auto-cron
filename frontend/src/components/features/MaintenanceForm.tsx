import { useEffect } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import {
  Alert,
  Button,
  FormField,
  Input,
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
  Spinner,
} from '@/components/ui';
import {
  MAINTENANCE_CATEGORIES,
  MAINTENANCE_TYPES,
  type Maintenance,
} from '@/api/types/maintenance';
import { maintenanceSchema, type MaintenanceFormData } from '@/schemas/maintenance.schema';
import { ApiError } from '@/api/client';
import { useServerFieldErrors } from '@/hooks/useServerFieldErrors';
import { todayIso } from '@/lib/format';

export interface MaintenanceFormProps {
  vehicleId: number;
  /** Ultimo km noto del veicolo (da stats), usato per pre-compilare il campo km su una nuova manutenzione */
  defaultKm?: number;
  defaultValues?: Partial<Maintenance>;
  isPending?: boolean;
  error?: unknown;
  onSubmit: (data: MaintenanceFormData) => void;
  submitLabel?: string;
  onCancel?: () => void;
}

export function MaintenanceForm({
  vehicleId,
  defaultKm,
  defaultValues,
  isPending,
  error,
  onSubmit,
  submitLabel,
  onCancel,
}: MaintenanceFormProps) {
  const { t } = useTranslation();

  const form = useForm<MaintenanceFormData>({
    resolver: zodResolver(maintenanceSchema),
    defaultValues: {
      vehicleId,
      performedAt: defaultValues?.performedAt ?? todayIso(),
      km: defaultValues?.km ?? defaultKm ?? 0,
      type: defaultValues?.type ?? 'oil_change',
      category: defaultValues?.category ?? 'scheduled',
      description: defaultValues?.description ?? '',
      cost: defaultValues?.cost ?? null,
      workshop: defaultValues?.workshop ?? null,
    },
  });

  useServerFieldErrors(form, error);

  // dirtyFields è dietro un Proxy: va letto in fase di render per attivare
  // la subscription, altrimenti nell'effect sotto risulta sempre stale.
  const { errors, dirtyFields } = form.formState;

  // defaultKm arriva async (query stats separata dal mount del form): se il
  // campo km non è ancora stato toccato dall'utente e non c'è un defaultValues
  // esplicito (edit), lo precompiliamo appena disponibile.
  useEffect(() => {
    if (defaultKm !== undefined && defaultValues?.km === undefined && !dirtyFields.km) {
      form.setValue('km', defaultKm);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [defaultKm, dirtyFields.km]);
  const tErr = (key: string | undefined) =>
    key ? t(`errors.${key}`, { defaultValue: key }) : undefined;

  return (
    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
      <input type="hidden" {...form.register('vehicleId', { valueAsNumber: true })} />

      <FormField label={t('maintenance.type')} error={tErr(errors.type?.message)} required>
        {(id) => (
          <Controller
            control={form.control}
            name="type"
            render={({ field }) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id={id}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {MAINTENANCE_TYPES.map((v) => (
                    <SelectItem key={v} value={v}>
                      {t(`maintenance.type_options.${v}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        )}
      </FormField>

      <FormField label={t('maintenance.category')} error={tErr(errors.category?.message)} required>
        {(id) => (
          <Controller
            control={form.control}
            name="category"
            render={({ field }) => (
              <Select value={field.value} onValueChange={field.onChange}>
                <SelectTrigger id={id}>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {MAINTENANCE_CATEGORIES.map((c) => (
                    <SelectItem key={c} value={c}>
                      {t(`maintenance.category_options.${c}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        )}
      </FormField>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <FormField label={t('maintenance.performed_at')} error={tErr(errors.performedAt?.message)} required>
          {(id) => <Input id={id} type="date" invalid={!!errors.performedAt} {...form.register('performedAt')} />}
        </FormField>
        <FormField label={t('maintenance.km')} error={tErr(errors.km?.message)} required>
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

      <FormField label={t('maintenance.description')} error={tErr(errors.description?.message)} required>
        {(id) => <Input id={id} invalid={!!errors.description} {...form.register('description')} />}
      </FormField>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <FormField label={t('maintenance.cost')} error={tErr(errors.cost?.message)} hint={t('common.optional')}>
          {(id) => (
            <Input
              id={id}
              type="text"
              inputMode="decimal"
              placeholder="0.00"
              invalid={!!errors.cost}
              {...form.register('cost', { setValueAs: (v) => (v === '' ? null : v) })}
            />
          )}
        </FormField>
        <FormField label={t('maintenance.workshop')} error={tErr(errors.workshop?.message)} hint={t('common.optional')}>
          {(id) => (
            <Input
              id={id}
              invalid={!!errors.workshop}
              {...form.register('workshop', { setValueAs: (v) => (v === '' ? null : v) })}
            />
          )}
        </FormField>
      </div>

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
