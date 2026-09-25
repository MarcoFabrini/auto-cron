import { useSearchParams } from 'react-router-dom';

/**
 * useVehicleIdParam — scope veicolo dalla query string (`?vehicleId=N`).
 * 0 = nessuna selezione. Valori non numerici/negativi vengono normalizzati a 0,
 * così i guard `vehicleId === 0` non lasciano passare NaN.
 */
export function useVehicleIdParam() {
  const [params, setParams] = useSearchParams();
  const raw = Number(params.get('vehicleId') ?? 0);
  const vehicleId = Number.isInteger(raw) && raw > 0 ? raw : 0;
  return { vehicleId, params, setParams };
}
