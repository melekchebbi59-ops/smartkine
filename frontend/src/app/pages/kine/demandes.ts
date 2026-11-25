import { Component, inject, signal } from '@angular/core';
import { Api, errorMessage } from '../../core/api';
import { Seance } from '../../core/models';
import { Alert } from '../../shared/alert';
import { Badge } from '../../shared/badge';
import { dateFr } from '../../shared/format';

/** US-16 : demandes de report / annulation envoyées par les patients. */
@Component({
  selector: 'app-kine-demandes',
  imports: [Alert, Badge],
  template: `
    <h1 class="page-title">Demandes des patients</h1>
    <app-alert [error]="erreur()" [success]="succes()" />
    <div class="row g-3">
      @for (s of demandes(); track s.id) {
        <div class="col-md-6 col-xl-4">
          <div class="card p-3 h-100">
            <div class="d-flex justify-content-between mb-2"><strong>{{ s.patient.fullName }}</strong><app-badge [statut]="s.statut" /></div>
            <div class="small">Séance prévue : <strong>{{ dateFr(s.dateHeure) }}</strong></div>
            @if (s.statut === 'report_demande') {
              <div class="small text-warning-emphasis">Nouvelle date proposée : <strong>{{ dateFr(s.dateProposee) }}</strong></div>
            }
            @if (s.motifDemande) { <p class="small bg-light rounded p-2 mt-2 mb-0">« {{ s.motifDemande }} »</p> }
            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-sm btn-success flex-fill" (click)="traiter(s, 'valider')"><i class="bi bi-check-lg"></i> Accepter</button>
              <button class="btn btn-sm btn-outline-danger flex-fill" (click)="traiter(s, 'refuser')"><i class="bi bi-x-lg"></i> Refuser</button>
            </div>
          </div>
        </div>
      } @empty {
        <div class="col-12"><div class="card empty"><i class="bi bi-inbox fs-2"></i><div>Aucune demande en attente.</div></div></div>
      }
    </div>
  `,
})
export class KineDemandes {
  private api = inject(Api);
  dateFr = dateFr;

  demandes = signal<Seance[]>([]);
  erreur = signal<string | null>(null);
  succes = signal<string | null>(null);

  constructor() {
    this.charger();
  }

  charger(): void {
    this.api.get<Seance[]>('/kine/seances/demandes').subscribe(l => this.demandes.set(l));
  }

  traiter(s: Seance, decision: 'valider' | 'refuser'): void {
    const type = s.statut === 'report_demande' ? 'report' : 'annulation';
    this.erreur.set(null);
    this.api.post<{ message: string }>(`/kine/seances/${s.id}/${type}/${decision}`).subscribe({
      next: () => { this.succes.set(decision === 'valider' ? 'Demande acceptée, le patient est notifié.' : 'Demande refusée, le patient est notifié.'); this.charger(); },
      error: e => this.erreur.set(errorMessage(e)),
    });
  }
}
