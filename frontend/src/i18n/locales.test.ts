import { describe, expect, it } from 'vitest';
import it_ from './locales/it.json';
import en from './locales/en.json';

function flatten(obj: unknown, prefix = ''): string[] {
  if (obj === null || typeof obj !== 'object') return [prefix];
  return Object.entries(obj as Record<string, unknown>).flatMap(([k, v]) => flatten(v, prefix ? `${prefix}.${k}` : k));
}

describe('traduzioni', () => {
  const italian = new Set(flatten(it_));
  const english = new Set(flatten(en));

  it('ogni chiave italiana esiste anche in inglese', () => {
    expect([...italian].filter((k) => !english.has(k))).toEqual([]);
  });

  it('ogni chiave inglese esiste anche in italiano', () => {
    expect([...english].filter((k) => !italian.has(k))).toEqual([]);
  });
});
