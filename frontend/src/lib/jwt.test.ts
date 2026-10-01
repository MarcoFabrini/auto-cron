import { describe, expect, it } from 'vitest';
import { activeOrgIdFromToken } from './jwt';

const token = (payload: object) => `h.${btoa(JSON.stringify(payload)).replace(/=+$/, '')}.s`;

describe('activeOrgIdFromToken', () => {
  it('legge active_org_id (numero o stringa)', () => {
    expect(activeOrgIdFromToken(token({ active_org_id: 7 }))).toBe(7);
    expect(activeOrgIdFromToken(token({ active_org_id: '12' }))).toBe(12);
  });

  it.each([null, undefined, '', 'abc', 'a.b.c', token({}), token({ active_org_id: 0 }), token({ active_org_id: 'x' })])(
    'ritorna null per %s',
    (raw) => {
      expect(activeOrgIdFromToken(raw as string | null | undefined)).toBeNull();
    },
  );
});
