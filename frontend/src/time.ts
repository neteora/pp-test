// Il server salva tutto in UTC; l'utente vede e inserisce gli orari nel proprio fuso.
export function toInputValue(iso: string | null | undefined, tz: string): string {
  if (!iso) return '';
  const formatted = new Intl.DateTimeFormat('sv-SE', {
    timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
  }).format(new Date(iso));
  return formatted.replace(' ', 'T'); // YYYY-MM-DDTHH:mm
}

export function display(iso: string | null | undefined, tz: string): string {
  if (!iso) return '—';
  return new Intl.DateTimeFormat('it-IT', { timeZone: tz, dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
}

// Senza offset: il backend interpreta il valore nel fuso dell'utente
export const fromInputValue = (v: string): string | null => (v ? `${v}:00` : null);
