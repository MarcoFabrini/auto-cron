import { z } from 'zod';

/**
 * Profilo: nome, cognome, email (dialog unico). La lingua è unificata con la
 * UI e si cambia dalle Preferenze (Impostazioni) — vedi SettingsPage.changeLanguage.
 */
export const profileSchema = z.object({
  firstName: z.string().min(1, 'account.first_name.required').max(100),
  lastName: z.string().min(1, 'account.last_name.required').max(100),
  email: z.string().min(1, 'account.email.required').email('account.email.invalid').max(180),
});
export type ProfileFormData = z.infer<typeof profileSchema>;

/** Cambio password con conferma. Match backend ChangePasswordRequest. */
export const changePasswordSchema = z
  .object({
    currentPassword: z.string().min(1, 'account.current_password.required'),
    newPassword: z.string().min(8, 'account.password.min'),
    confirmPassword: z.string().min(1, 'account.confirm_password.required'),
  })
  .refine((d) => d.newPassword === d.confirmPassword, {
    message: 'account.password.mismatch',
    path: ['confirmPassword'],
  });
export type ChangePasswordFormData = z.infer<typeof changePasswordSchema>;

/** Modifica organizzazione (solo owner/admin). */
export const organizationSchema = z.object({
  name: z.string().min(1, 'account.org_name.required').max(150),
});
export type OrganizationFormData = z.infer<typeof organizationSchema>;

/** Invito di un membro all'organizzazione (owner/admin). Owner non assegnabile via invito. */
export const inviteMemberSchema = z.object({
  email: z.string().min(1, 'account.email.required').email('account.email.invalid').max(180),
  role: z.enum(['member', 'admin']),
});
export type InviteMemberFormData = z.infer<typeof inviteMemberSchema>;

/** Registrazione contestuale all'accettazione invito (email presa dall'invito). */
export const acceptInviteRegisterSchema = z.object({
  firstName: z.string().min(1, 'account.first_name.required').max(100),
  lastName: z.string().min(1, 'account.last_name.required').max(100),
  password: z.string().min(8, 'account.password.min'),
});
export type AcceptInviteRegisterFormData = z.infer<typeof acceptInviteRegisterSchema>;
