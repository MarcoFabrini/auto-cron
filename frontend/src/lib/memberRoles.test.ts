import { describe, it, expect } from 'vitest';
import { assignableRoles, isDemotion, roleChangeKind } from './memberRoles';

describe('assignableRoles', () => {
  it("l'owner può portare admin e member a qualsiasi altro ruolo", () => {
    expect(assignableRoles('owner', 'admin', false, 1)).toEqual(['owner', 'member']);
    expect(assignableRoles('owner', 'member', false, 1)).toEqual(['owner', 'admin']);
  });

  it("l'owner può cambiare un altro owner o sé stesso solo se gli owner sono almeno due", () => {
    expect(assignableRoles('owner', 'owner', false, 2)).toEqual(['admin', 'member']);
    expect(assignableRoles('owner', 'owner', true, 2)).toEqual(['admin', 'member']);
    expect(assignableRoles('owner', 'owner', true, 1)).toEqual([]);
    expect(assignableRoles('owner', 'owner', false, 1)).toEqual([]);
  });

  it("l'admin può solo promuovere un member ad admin", () => {
    expect(assignableRoles('admin', 'member', false, 1)).toEqual(['admin']);
  });

  it("l'admin non tocca altri admin, owner né sé stesso", () => {
    expect(assignableRoles('admin', 'admin', false, 1)).toEqual([]);
    expect(assignableRoles('admin', 'admin', true, 1)).toEqual([]);
    expect(assignableRoles('admin', 'owner', false, 2)).toEqual([]);
  });

  it('il member non cambia nessun ruolo', () => {
    expect(assignableRoles('member', 'member', false, 1)).toEqual([]);
    expect(assignableRoles('member', 'admin', false, 1)).toEqual([]);
  });
});

describe('roleChangeKind e isDemotion', () => {
  it('classifica la direzione del cambio', () => {
    expect(roleChangeKind('member', 'owner')).toBe('to_owner');
    expect(roleChangeKind('admin', 'owner')).toBe('to_owner');
    expect(roleChangeKind('owner', 'admin')).toBe('owner_demoted');
    expect(roleChangeKind('owner', 'member')).toBe('owner_demoted');
    expect(roleChangeKind('admin', 'member')).toBe('admin_to_member');
    expect(roleChangeKind('member', 'admin')).toBe('member_to_admin');
  });

  it('un declassamento va verso un ruolo meno privilegiato', () => {
    expect(isDemotion('owner', 'admin')).toBe(true);
    expect(isDemotion('admin', 'member')).toBe(true);
    expect(isDemotion('member', 'admin')).toBe(false);
    expect(isDemotion('admin', 'owner')).toBe(false);
  });
});
