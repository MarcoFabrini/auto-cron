import { describe, expect, it } from 'vitest';
import { safeNextPath } from './safeNext';

describe('safeNextPath', () => {
  it('accetta path interni con query', () => {
    expect(safeNextPath('/vehicles/3/edit')).toBe('/vehicles/3/edit');
    expect(safeNextPath('/accept-invite?token=abc')).toBe('/accept-invite?token=abc');
  });

  it.each([null, undefined, '', 'vehicles', 'https://evil.test', '//evil.test', '/\\evil.test', 'javascript:alert(1)'])(
    'scarta %s',
    (raw) => {
      expect(safeNextPath(raw)).toBe('/');
    },
  );
});
