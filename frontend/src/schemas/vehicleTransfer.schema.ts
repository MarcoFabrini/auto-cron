import { z } from 'zod';

/**
 * Trasferimento di proprietà del veicolo. Specchia `VehicleTransferRequest`: `userId` positivo,
 * `keepAccess` booleano. Il form parte da `userId: 0` (nessuna scelta) e la validazione lo rifiuta,
 * quindi il submit non parte senza un destinatario.
 */
export const vehicleTransferSchema = z.object({
  userId: z.number().int().positive('vehicle.transfer.recipient_required'),
  keepAccess: z.boolean(),
});
export type VehicleTransferFormData = z.infer<typeof vehicleTransferSchema>;
