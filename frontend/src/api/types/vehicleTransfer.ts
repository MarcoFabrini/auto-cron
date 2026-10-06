import { z } from 'zod';

/** Membro dell'organizzazione a cui si può cedere il veicolo: solo nome, mai l'email. */
export const transferCandidateSchema = z.object({
  id: z.number().int().positive(),
  firstName: z.string(),
  lastName: z.string(),
});
export type TransferCandidate = z.infer<typeof transferCandidateSchema>;

export const transferCandidatesSchema = z.array(transferCandidateSchema);

/** Corpo di `POST /api/vehicles/{id}/transfer` (stesso contratto di `VehicleTransferRequest`). */
export interface TransferVehicleDto {
  userId: number;
  keepAccess: boolean;
}
