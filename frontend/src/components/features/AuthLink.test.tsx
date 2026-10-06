import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { AuthLink } from './AuthLink';

describe('AuthLink', () => {
  it('è un link con bersaglio da 44px (jsdom non ha layout: si verifica la classe)', () => {
    render(
      <MemoryRouter>
        <AuthLink to="/login">Accedi</AuthLink>
      </MemoryRouter>,
    );

    const link = screen.getByRole('link', { name: 'Accedi' });
    expect(link).toHaveAttribute('href', '/login');
    expect(link).toHaveClass('min-h-touch', 'min-w-touch');
  });

  it('accetta classi aggiuntive senza perdere quelle del bersaglio', () => {
    render(
      <MemoryRouter>
        <AuthLink to="/forgot-password" className="text-sm">
          Password dimenticata?
        </AuthLink>
      </MemoryRouter>,
    );

    expect(screen.getByRole('link', { name: 'Password dimenticata?' })).toHaveClass('text-sm', 'min-h-touch');
  });
});
