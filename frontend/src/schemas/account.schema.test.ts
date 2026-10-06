import { describe, it, expect } from 'vitest';
import {
  acceptInviteRegisterSchema,
  changePasswordSchema,
  inviteMemberSchema,
  profileSchema,
} from './account.schema';

describe('account.schema', () => {
  it('profileSchema: ripulisce gli spazi dell\'email', () => {
    const r = profileSchema.parse({ firstName: 'A', lastName: 'B', email: ' a@b.it ' });
    expect(r.email).toBe('a@b.it');
  });

  it('inviteMemberSchema: ripulisce gli spazi dell\'email', () => {
    expect(inviteMemberSchema.parse({ email: ' a@b.it ', role: 'member' }).email).toBe('a@b.it');
  });

  it('changePasswordSchema: la nuova password ha massimo 200 caratteri, la corrente no', () => {
    const base = { currentPassword: 'c'.repeat(300), newPassword: 'a'.repeat(200), confirmPassword: 'a'.repeat(200) };
    expect(changePasswordSchema.safeParse(base).success).toBe(true);
    const long = 'a'.repeat(201);
    const r = changePasswordSchema.safeParse({ ...base, newPassword: long, confirmPassword: long });
    expect(r.success).toBe(false);
    expect(r.error?.issues[0]?.message).toBe('common.too_long');
  });

  it('acceptInviteRegisterSchema: password oltre 200 caratteri rifiutata', () => {
    const base = { firstName: 'A', lastName: 'B' };
    expect(acceptInviteRegisterSchema.safeParse({ ...base, password: 'a'.repeat(200) }).success).toBe(true);
    expect(acceptInviteRegisterSchema.safeParse({ ...base, password: 'a'.repeat(201) }).success).toBe(false);
  });
});
