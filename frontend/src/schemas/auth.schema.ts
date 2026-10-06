import { z } from 'zod';

/**
 * Login: solo campi obbligatori (niente `.email()`/`min(8)` — il backend non li impone e non
 * deve restare fuori chi ha credenziali valide). L'email viene ripulita dagli spazi.
 */
export const loginSchema = z.object({
  email: z.string().trim().min(1, 'account.email.required'),
  password: z.string().min(1, 'account.password.required'),
});
export type LoginFormData = z.infer<typeof loginSchema>;

/** Registrazione con conferma password. */
export const registerSchema = z
  .object({
    firstName: z.string().min(1, 'account.first_name.required').max(100),
    lastName: z.string().min(1, 'account.last_name.required').max(100),
    email: z.string().trim().min(1, 'account.email.required').email('account.email.invalid').max(180),
    password: z.string().min(8, 'account.password.min').max(200, 'common.too_long'),
    confirmPassword: z.string().min(1, 'account.confirm_password.required'),
  })
  .refine((d) => d.password === d.confirmPassword, {
    message: 'account.password.mismatch',
    path: ['confirmPassword'],
  });
export type RegisterFormData = z.infer<typeof registerSchema>;

/** Password dimenticata: solo email. */
export const forgotPasswordSchema = z.object({
  email: z.string().trim().min(1, 'account.email.required').email('account.email.invalid').max(180),
});
export type ForgotPasswordFormData = z.infer<typeof forgotPasswordSchema>;

/** Reset password (da link email) con conferma. */
export const resetPasswordSchema = z
  .object({
    password: z.string().min(8, 'account.password.min').max(200, 'common.too_long'),
    confirmPassword: z.string().min(1, 'account.confirm_password.required'),
  })
  .refine((d) => d.password === d.confirmPassword, {
    message: 'account.password.mismatch',
    path: ['confirmPassword'],
  });
export type ResetPasswordFormData = z.infer<typeof resetPasswordSchema>;
