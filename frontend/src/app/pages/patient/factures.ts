import { Component, inject, signal } from '@angular/core';
import { Api } from '../../core/api';
import { Facture } from '../../core/models';
import { Badge } from '../../shared/badge';
import { dateFr, dt } from '../../shared/format';

/** US-10 : factures, paiements effectués et reste à payer du patient. */
@Component({
  selector: 'app-patient-factures',
  imports: [Badge],
  template: `
    <h1 class="page-title">Mes factures</h1>
    @if (resume(); as r) {
      <div class="row g-3 mb-3">
        <div class="col-4"><div class="card stat-card p-3"><div class="small text-muted">Total facturé</div><div class="value fs-4">{{ dt(r.total) }}</div></div></div>
        <div class="col-4"><div class="card stat-card p-3"><div class="small text-muted">Déjà payé</div><div class="value fs-4 text-success">{{ dt(r.paye) }}</div></div></div>
        <div class="col-4"><div class="card stat-card p-3" [class.border]="+r.reste > 0" [class.border-danger]="+r.reste > 0"><div class="small text-muted">Reste à payer</div><div class="value fs-4" [class.text-danger]="+r.reste > 0">{{ dt(r.reste) }}</div></div></div>
      </div>
    }
    @for (f of factures(); track f.id) {
      <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-start">
          <div><h6 class="fw-bold mb-0">{{ f.numero }}</h6><div class="small text-muted">Émise le {{ dateFr(f.dateEmission, false) }}</div></div>
          <app-badge [statut]="f.statut" kind="facture" />
        </div>
        <div class="table-responsive mt-2">
          <table class="table table-sm small mb-2">
            <tbody>
              @for (s of f.seances; track s.id) { <tr><td>Séance du {{ dateFr(s.dateHeure) }}</td><td class="text-end">{{ dt(s.prix) }}</td></tr> }
              <tr class="fw-bold"><td>Total</td><td class="text-end">{{ dt(f.montantTotal) }}</td></tr>
              @for (p of f.paiements; track p.id) { <tr class="text-success"><td>Paiement du {{ dateFr(p.datePaiement, false) }}</td><td class="text-end">− {{ dt(p.montant) }}</td></tr> }
              <tr class="fw-bold" [class.text-danger]="+f.resteAPayer > 0"><td>Reste à payer</td><td class="text-end">{{ dt(f.resteAPayer) }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    } @empty {
      <div class="card empty"><i class="bi bi-receipt fs-2"></i><div>Aucune facture pour le moment.</div></div>
    }
  `,
})
export class PatientFactures {
  private api = inject(Api);
  dateFr = dateFr;
  dt = dt;
  factures = signal<Facture[]>([]);
  resume = signal<{ total: string; paye: string; reste: string } | null>(null);

  constructor() {
    this.api.get<{ resume: { total: string; paye: string; reste: string }; factures: Facture[] }>('/patient/factures').subscribe(r => {
      this.resume.set(r.resume);
      this.factures.set(r.factures);
    });
  }
}
