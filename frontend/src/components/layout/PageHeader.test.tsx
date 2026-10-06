import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import '@/i18n';
import { PageHeader } from './PageHeader';

describe('PageHeader', () => {
  it('con un titolo visibile rende quello come unico h1', () => {
    render(<PageHeader title="Golf" srTitle="Manutenzioni" />);

    const h1 = screen.getAllByRole('heading', { level: 1 });
    expect(h1).toHaveLength(1);
    expect(h1[0]).toHaveTextContent('Golf');
    expect(h1[0]).not.toHaveClass('sr-only');
  });

  it('senza titolo visibile rende un h1 sr-only con il nome della pagina', () => {
    render(<PageHeader srTitle="Manutenzioni" action={<button type="button">Nuovo</button>} />);

    const h1 = screen.getByRole('heading', { level: 1, name: 'Manutenzioni' });
    expect(h1).toHaveClass('sr-only');
    expect(screen.getByRole('button', { name: 'Nuovo' })).toBeInTheDocument();
  });

  it('con il solo srTitle rende solo l\'h1 sr-only', () => {
    const { container } = render(<PageHeader srTitle="Dashboard" />);

    expect(container.children).toHaveLength(1);
    expect(screen.getByRole('heading', { level: 1, name: 'Dashboard' })).toHaveClass('sr-only');
  });

  it('senza nulla da mostrare né annunciare non rende niente', () => {
    const { container } = render(<PageHeader />);
    expect(container).toBeEmptyDOMElement();
  });
});
