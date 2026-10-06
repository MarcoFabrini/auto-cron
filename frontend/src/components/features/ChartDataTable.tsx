export interface ChartDataTableProps {
  /** Titolo del grafico: dà il nome accessibile alla tabella. */
  caption: string;
  /** Intestazioni di colonna; la prima descrive l'etichetta di riga (mese, categoria…). */
  columns: string[];
  /** Una riga per punto dati, già formattata: la prima cella è l'etichetta della riga. */
  rows: string[][];
}

/**
 * Alternativa testuale di un grafico: tabella vera, visibile solo agli screen reader. Il wrapper
 * porta `sr-only` e non la tabella, che su alcuni browser perde il layout di tabella se posizionata.
 */
export function ChartDataTable({ caption, columns, rows }: ChartDataTableProps) {
  return (
    <div className="sr-only">
      <table>
        <caption>{caption}</caption>
        <thead>
          <tr>
            {columns.map((column) => (
              <th key={column} scope="col">
                {column}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map(([label = '', ...cells]) => (
            <tr key={label}>
              <th scope="row">{label}</th>
              {cells.map((cell, i) => (
                <td key={columns[i + 1] ?? i}>{cell}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
