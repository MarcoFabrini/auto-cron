import { describe, it, expect } from 'vitest';
import { render } from '@testing-library/react';
import { Car } from 'lucide-react';
import { CardContent } from './Card';
import { StatCard } from '@/components/features/StatCard';

/**
 * Regressione: da desktop le card senza CardHeader avevano padding-top 0 e
 * 24px sotto (md:p-6 md:pt-0 del default non veniva rimosso da un semplice
 * `p-4` nel className), quindi icona e testo risultavano schiacciati in alto.
 */
describe('CardContent', () => {
  it('di default tiene pt-0 (sta sotto un CardHeader)', () => {
    const { container } = render(<CardContent>x</CardContent>);
    const cls = (container.firstElementChild as HTMLElement).className;
    expect(cls).toContain('pt-0');
    expect(cls).toContain('md:p-6');
  });

  it('standalone: padding uniforme, nessun pt-0 né md:p-6 da desktop', () => {
    const { container } = render(<CardContent standalone>x</CardContent>);
    const cls = (container.firstElementChild as HTMLElement).className;
    expect(cls).toBe('p-4');
  });

  it('standalone mantiene le classi del chiamante', () => {
    const { container } = render(
      <CardContent standalone className="flex items-center gap-4">
        x
      </CardContent>,
    );
    const cls = (container.firstElementChild as HTMLElement).className;
    expect(cls).toContain('flex items-center gap-4');
    expect(cls).not.toMatch(/pt-0|md:p-6/);
  });

  it('StatCard non eredita il padding-top zero del default', () => {
    const { container } = render(<StatCard Icon={Car} label="Veicoli" value={1} />);
    const content = container.querySelector('.flex.items-center') as HTMLElement;
    expect(content.className).not.toMatch(/pt-0|md:p-6/);
  });
});
