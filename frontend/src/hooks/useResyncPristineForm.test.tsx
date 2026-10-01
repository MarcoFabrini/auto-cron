import { describe, expect, it } from 'vitest';
import { act, renderHook } from '@testing-library/react';
import { useForm } from 'react-hook-form';
import { useResyncPristineForm } from './useResyncPristineForm';

interface Values {
  description: string;
}

function setup(initial: { description: string }) {
  return renderHook(
    ({ source }: { source: { description: string } }) => {
      const values: Values = { description: source.description };
      const form = useForm<Values>({ defaultValues: values });
      useResyncPristineForm(form, source, values);
      return form;
    },
    { initialProps: { source: initial } },
  );
}

describe('useResyncPristineForm', () => {
  it('un form non toccato si riallinea al dato più recente (refetch dopo la cache)', () => {
    const { result, rerender } = setup({ description: 'vecchio' });

    rerender({ source: { description: 'nuovo dal server' } });

    expect(result.current.getValues('description')).toBe('nuovo dal server');
  });

  it('non cancella ciò che l\'utente sta digitando', () => {
    const { result, rerender } = setup({ description: 'vecchio' });

    act(() => {
      result.current.setValue('description', 'modificato da me', { shouldDirty: true });
    });
    rerender({ source: { description: 'nuovo dal server' } });

    expect(result.current.getValues('description')).toBe('modificato da me');
  });
});
