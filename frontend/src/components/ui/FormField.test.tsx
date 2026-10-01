import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { FormField } from './FormField';
import { Input } from './Input';

describe('FormField', () => {
  it('associa hint ed errore all\'input stesso (aria-describedby), non a un wrapper', () => {
    render(<FormField label="Km" hint="Chilometri attuali">{(id) => <Input id={id} />}</FormField>);

    const input = screen.getByLabelText('Km');
    const describedBy = input.getAttribute('aria-describedby');
    expect(describedBy).toBeTruthy();
    expect(document.getElementById(describedBy!)).toHaveTextContent('Chilometri attuali');
  });

  it('l\'errore ha la precedenza sull\'hint e resta collegato al campo', () => {
    render(
      <FormField label="Km" hint="Chilometri attuali" error="Numero non valido">
        {(id) => <Input id={id} />}
      </FormField>,
    );

    const input = screen.getByLabelText('Km');
    expect(document.getElementById(input.getAttribute('aria-describedby')!)).toHaveTextContent('Numero non valido');
  });

  it('senza hint né errore non aggiunge aria-describedby', () => {
    render(<FormField label="Km">{(id) => <Input id={id} />}</FormField>);

    expect(screen.getByLabelText('Km')).not.toHaveAttribute('aria-describedby');
  });

  it('rispetta un aria-describedby già impostato dal child', () => {
    render(<FormField label="Km" hint="x">{(id) => <Input id={id} aria-describedby="mio" />}</FormField>);

    expect(screen.getByLabelText('Km')).toHaveAttribute('aria-describedby', 'mio');
  });
});
