import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

// Con `css: false` l'import ?raw torna vuoto: si legge il file dalla radice del frontend (cwd di vitest).
const css = readFileSync(resolve(process.cwd(), 'src/index.css'), 'utf8');

/** Corpo del blocco `@media (prefers-reduced-motion: reduce) { … }`, con le graffe annidate bilanciate. */
function reducedMotionBlock(): string {
  const start = css.indexOf('@media (prefers-reduced-motion: reduce)');
  if (start === -1) return '';
  const open = css.indexOf('{', start);
  let depth = 0;
  for (let i = open; i < css.length; i++) {
    if (css[i] === '{') depth++;
    if (css[i] === '}' && --depth === 0) return css.slice(open + 1, i);
  }
  return '';
}

describe('prefers-reduced-motion', () => {
  const block = reducedMotionBlock();

  it('esiste una media query per chi chiede meno movimento', () => {
    expect(block).not.toBe('');
  });

  it('azzera animazioni e transizioni di tutta l\'app (anche le classi di tailwindcss-animate)', () => {
    expect(block).toMatch(/animation-duration:\s*0\.01ms\s*!important/);
    expect(block).toMatch(/animation-iteration-count:\s*1\s*!important/);
    expect(block).toMatch(/transition-duration:\s*0\.01ms\s*!important/);
  });

  it('disattiva lo scroll morbido', () => {
    expect(block).toMatch(/scroll-behavior:\s*auto/);
  });

  it('lo skeleton non pulsa e lo spinner gira lentamente', () => {
    expect(block).toMatch(/\.animate-pulse\s*\{[^}]*animation:\s*none/);
    expect(block).toMatch(/\.animate-spin\s*\{[^}]*animation-duration:\s*3s/);
  });
});
