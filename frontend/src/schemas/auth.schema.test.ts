import { describe, it, expect } from 'vitest';
import { forgotPasswordSchema, loginSchema, registerSchema, resetPasswordSchema } from './auth.schema';

const registerBase = {
  firstName: 'Anna',
  lastName: 'Neri',
  email: 'anna@test.it',
  password: 'segreta-123',
  confirmPassword: 'segreta-123',
};

describe('auth.schema', () => {
  it('registerSchema: ripulisce gli spazi dell\'email prima di validarla', () => {
    const r = registerSchema.parse({ ...registerBase, email: '  anna@test.it  ' });
    expect(r.email).toBe('anna@test.it');
  });

  it('registerSchema: una password di 200 caratteri passa, di 201 no (come il backend)', () => {
    const ok = 'a'.repeat(200);
    expect(registerSchema.safeParse({ ...registerBase, password: ok, confirmPassword: ok }).success).toBe(true);
    const long = 'a'.repeat(201);
    const r = registerSchema.safeParse({ ...registerBase, password: long, confirmPassword: long });
    expect(r.success).toBe(false);
    expect(r.error?.issues[0]?.message).toBe('common.too_long');
  });

  it('registerSchema: un\'email di soli spazi è "obbligatoria", non "non valida"', () => {
    const r = registerSchema.safeParse({ ...registerBase, email: '   ' });
    expect(r.error?.issues[0]?.message).toBe('account.email.required');
  });

  it('forgotPasswordSchema: ripulisce gli spazi dell\'email', () => {
    expect(forgotPasswordSchema.parse({ email: ' a@b.it ' }).email).toBe('a@b.it');
  });

  it('resetPasswordSchema: password oltre 200 caratteri rifiutata', () => {
    const long = 'a'.repeat(201);
    expect(resetPasswordSchema.safeParse({ password: long, confirmPassword: long }).success).toBe(false);
  });

  it('loginSchema: email ripulita, campi vuoti rifiutati', () => {
    expect(loginSchema.parse({ email: ' a@b.it ', password: 'x' })).toEqual({ email: 'a@b.it', password: 'x' });
    const r = loginSchema.safeParse({ email: '  ', password: '' });
    expect(r.error?.issues.map((i) => i.message).sort()).toEqual(['account.email.required', 'account.password.required']);
  });
});
