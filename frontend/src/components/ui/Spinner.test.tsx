import { beforeEach, describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import i18n from '@/i18n';
import { Spinner } from './Spinner';

describe('Spinner', () => {
  beforeEach(async () => {
    await i18n.changeLanguage('it');
  });

  it("espone di default l'etichetta tradotta, non un inglese fisso", () => {
    render(<Spinner />);
    expect(screen.getByRole('status', { name: 'Caricamento in corso' })).toBeInTheDocument();
  });

  it("segue la lingua dell'interfaccia", async () => {
    await i18n.changeLanguage('en');
    render(<Spinner />);
    expect(screen.getByRole('status', { name: 'Loading' })).toBeInTheDocument();
  });

  it('una label esplicita ha la precedenza', () => {
    render(<Spinner label="Invio in corso" />);
    expect(screen.getByRole('status', { name: 'Invio in corso' })).toBeInTheDocument();
  });
});
