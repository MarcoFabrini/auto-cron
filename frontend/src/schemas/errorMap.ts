import { z } from 'zod';

/**
 * Messaggi zod di default (inglese, es. "Expected integer, received float") → chiavi i18n
 * `errors.common.*`, che `tErr` nei form traduce. Si applica solo dove lo schema NON ha un messaggio
 * esplicito (quelli espliciti hanno la precedenza in zod).
 */
const errorMap: z.ZodErrorMap = (issue, ctx) => {
  switch (issue.code) {
    case z.ZodIssueCode.invalid_type:
      if (issue.received === 'undefined' || issue.received === 'null') return { message: 'common.required' };
      if (issue.expected === 'integer') return { message: 'common.must_be_integer' };
      if (issue.expected === 'number') return { message: 'common.invalid_number' };
      return { message: 'common.invalid_value' };
    case z.ZodIssueCode.too_small:
      return { message: issue.type === 'string' || issue.type === 'array' ? 'common.too_short' : 'common.too_small' };
    case z.ZodIssueCode.too_big:
      return { message: issue.type === 'string' || issue.type === 'array' ? 'common.too_long' : 'common.too_big' };
    case z.ZodIssueCode.invalid_string:
    case z.ZodIssueCode.invalid_enum_value:
    case z.ZodIssueCode.invalid_literal:
      return { message: 'common.invalid_value' };
    default:
      return { message: ctx.defaultError };
  }
};

z.setErrorMap(errorMap);
