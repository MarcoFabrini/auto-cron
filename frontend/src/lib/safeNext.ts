/**
 * Destinazione post-login da un parametro `?next=`: solo path interni dell'app.
 * Scarta URL assoluti, `//host` (protocol-relative) e `/\host` (alcuni browser lo normalizzano a `//`).
 */
export function safeNextPath(raw: string | null | undefined): string {
  if (!raw || !raw.startsWith('/') || raw.startsWith('//') || raw.startsWith('/\\')) return '/';
  return raw;
}
