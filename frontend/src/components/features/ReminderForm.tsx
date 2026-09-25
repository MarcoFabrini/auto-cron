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
import { REMINDER_TYPES, type Reminder } from '@/api/types/reminder';
import { reminderSchema, type ReminderFormData } from '@/schemas/reminder.schema';
import { ApiError } from '@/api/client';
import { useServerFieldErrors } from '@/hooks/useServerFieldErrors';

export interface ReminderFormProps {
  vehicleId: number;
  defaultValues?: Partial<Reminder>;
  isPending?: boolean;
  error?: unknown;
  onSubmit: (data: ReminderFormData) => void;
  submitLabel?: string;
  onCancel?: () => void;
}

export function ReminderForm({
  vehicleId,
  defaultValues,
  isPending,
  error,
  onSubmit,
  submitLabel,
  onCancel,
}: ReminderFormProps) {
  const { t } = useTranslation();

  const form = useForm<ReminderFormData>({
    resolver: zodResolver(reminderSchema),
    defaultValues: {
      vehicleId,
      type: defaultValues?.type ?? 'custom',
      description: defaultValues?.description ?? '',
      dueDate: defaultValues?.dueDate ?? null,
      dueKm: defaultValues?.dueKm ?? null,
      notifyDaysBefore: defaultValues?.notifyDaysBefore ?? 30,
    },
  });

  useServerFieldErrors(form, error);

  const errors = form.formState.errors;
  const tErr = (key: string | undefined) =>
    key ? t(`errors.${key}`, { defaultValue: key }) : undefined;

  return (
    <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
      <input type="hidden" {...form.register('vehicleId', { valueAsNumber: true })} />

      <FormField label={t('reminder.type')} error={tErr(errors.type?.message)} required>
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
                  {REMINDER_TYPES.map((v) => (
                    <SelectItem key={v} value={v}>
                      {t(`reminder.type_options.${v}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          />
        )}
      </FormField>

      <FormField label={t('reminder.description')} error={tErr(errors.description?.message)} required>
        {(id) => <Input id={id} invalid={!!errors.description} {...form.register('description')} />}
      </FormField>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <FormField
          label={t('reminder.due_date')}
          error={tErr(errors.dueDate?.message)}
          hint={t('reminder.due_hint')}
        >
          {(id) => (
            <Input
              id={id}
              type="date"
              invalid={!!errors.dueDate}
              {...form.register('dueDate', { setValueAs: (v) => (v === '' ? null : v) })}
            />
          )}
        </FormField>
        <FormField label={t('reminder.due_km')} error={tErr(errors.dueKm?.message)}>
          {(id) => (
            <Input
              id={id}
              type="number"
              inputMode="numeric"
              invalid={!!errors.dueKm}
              {...form.register('dueKm', {
                setValueAs: (v) => (v === '' || v == null ? null : Number(v)),
              })}
            />
          )}
        </FormField>
      </div>

      <FormField
        label={t('reminder.notify_days_before')}
        error={tErr(errors.notifyDaysBefore?.message)}
        hint={t('reminder.notify_hint')}
        required
      >
        {(id) => (
          <Input
            id={id}
            type="number"
            inputMode="numeric"
            invalid={!!errors.notifyDaysBefore}
            {...form.register('notifyDaysBefore', { valueAsNumber: true })}
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
