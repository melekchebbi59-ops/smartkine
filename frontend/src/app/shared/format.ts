/** Petites fonctions d'affichage partagées. */

export function dateFr(iso: string | null | undefined, avecHeure = true): string {
  if (!iso) {
    return '—';
  }
  const d = new Date(iso);
  return avecHeure
    ? d.toLocaleString('fr-FR', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : d.toLocaleDateString('fr-FR');
}

export function dt(montant: string | number | null | undefined): string {
  return Number(montant ?? 0).toFixed(2) + ' DT';
}

/** Valeur pour un <input type="datetime-local"> */
export function toInputDateTime(iso: string | null | undefined): string {
  return iso ? iso.slice(0, 16) : '';
}

export const STATUTS_SEANCE: Record<string, { label: string; css: string }> = {
  planifiee: { label: 'Planifiée', css: 'text-bg-primary' },
  effectuee: { label: 'Effectuée', css: 'text-bg-success' },
  annulee: { label: 'Annulée', css: 'text-bg-secondary' },
  report_demande: { label: 'Report demandé', css: 'text-bg-warning' },
  annulation_demandee: { label: 'Annulation demandée', css: 'text-bg-danger' },
};

export const STATUTS_DEMANDE: Record<string, { label: string; css: string }> = {
  en_attente: { label: 'En attente', css: 'text-bg-warning' },
  acceptee: { label: 'Acceptée', css: 'text-bg-success' },
  refusee: { label: 'Refusée', css: 'text-bg-secondary' },
  valide: { label: 'Validée', css: 'text-bg-success' },
  refuse: { label: 'Refusée', css: 'text-bg-secondary' },
};

export const STATUTS_FACTURE: Record<string, { label: string; css: string }> = {
  payee: { label: 'Payée', css: 'text-bg-success' },
  partielle: { label: 'Partielle', css: 'text-bg-warning' },
  impayee: { label: 'Impayée', css: 'text-bg-danger' },
};
