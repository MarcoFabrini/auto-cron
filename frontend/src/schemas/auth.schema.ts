import { z } from 'zod';

/** Registrazione con conferma password. */
export const registerSchema = z
  .object({
    firstName: z.string().min(1, 'account.first_name.required').max(100),
    lastName: z.string().min(1, 'account.last_name.required').max(100),
    email: z.string().min(1, 'account.email.required').email('account.email.invalid').max(180),
    password: z.string().min(8, 'account.password.min'),
    confirmPassword: z.string().min(1, 'account.confirm_password.required'),
  })
  .refine((d) => d.password === d.confirmPassword, {
    message: 'account.password.mismatch',
    path: ['confirmPassword'],
  });
export type RegisterFormData = z.infer<typeof registerSchema>;

/** Password dimenticata: solo email. */
export const forgotPasswordSchema = z.object({
  email: z.string().min(1, 'account.email.required').email('account.email.invalid').max(180),
});
export type ForgotPasswordFormData = z.infer<typeof forgotPasswordSchema>;

/** Reset password (da link email) con conferma. */
export const resetPasswordSchema = z
  .object({
    password: z.string().min(8, 'account.password.min'),
    confirmPassword: z.string().min(1, 'account.confirm_password.required'),
  })
  .refine((d) => d.password === d.confirmPassword, {
    message: 'account.password.mismatch',
    path: ['confirmPassword'],
  });
export type ResetPasswordFormData = z.infer<typeof resetPasswordSchema>;
