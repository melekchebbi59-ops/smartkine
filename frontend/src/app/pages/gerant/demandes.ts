import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Observable } from 'rxjs';
import { Api, errorMessage } from '../../core/api';
import { DemandeRdv } from '../../core/models';
import { Alert } from '../../shared/alert';
import { Badge } from '../../shared/badge';
import { dateFr, toInputDateTime } from '../../shared/format';

/** US-23 : traitement des demandes de premier rendez-vous. */
@Component({
  selector: 'app-gerant-demandes',
  imports: [FormsModule, Alert, Badge],
  template: `
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h1 class="page-title mb-0">Demandes de premier RDV</h1>
      <select class="form-select w-auto" [(ngModel)]="filtre" (change)="charger()">
        <option value="en_attente">En attente</option>
        <option value="acceptee">Acceptées</option>
        <option value="refusee">Refusées</option>
        <option value="">Toutes</option>
      </select>
    </div>
    <app-alert [error]="erreur()" [success]="succes()" />

    <div class="row g-3">
      @for (d of demandes(); track d.id) {
        <div class="col-md-6 col-xl-4">
          <div class="card h-100 p-3">
            <div class="d-flex justify-content-between">
              <h6 class="fw-bold mb-1">{{ d.prenom }} {{ d.nom }}</h6>
              <app-badge [statut]="d.statut" kind="demande" />
            </div>
            <div class="small text-muted mb-2">#{{ d.id }} — reçue le {{ dateFr(d.createdAt) }}</div>
            <div class="small"><i class="bi bi-telephone me-1"></i>{{ d.telephone }}</div>
            <div class="small"><i class="bi bi-envelope me-1"></i>{{ d.email }}</div>
            <div class="small"><i class="bi bi-calendar me-1"></i>Souhaitée : <strong>{{ dateFr(d.dateSouhaitee) }}</strong></div>
            @if (d.dateRdvConfirmee) { <div class="small text-success"><i class="bi bi-check2 me-1"></i>Confirmée : {{ dateFr(d.dateRdvConfirmee) }}</div> }
            @if (d.motif) { <p class="small bg-light rounded p-2 mt-2 mb-0">{{ d.motif }}</p> }
            @if (d.statut === 'en_attente') {
              <div class="mt-3">
                <label class="form-label small mb-1">Date du RDV</label>
                <input type="datetime-local" class="form-control form-control-sm mb-2" [(ngModel)]="dates[d.id]">
                <div class="d-flex gap-2">
                  <button class="btn btn-sm btn-success flex-fill" (click)="accepter(d)"><i class="bi bi-check-lg"></i> Accepter</button>
                  <button class="btn btn-sm btn-outline-danger flex-fill" (click)="refuser(d)"><i class="bi bi-x-lg"></i> Refuser</button>
                </div>
              </div>
            }
          </div>
        </div>
      } @empty {
        <div class="col-12"><div class="card empty">Aucune demande.</div></div>
      }
    </div>
  `,
})
export class GerantDemandes {
  private api = inject(Api);
  dateFr = dateFr;

  filtre = 'en_attente';
  demandes = signal<DemandeRdv[]>([]);
  dates: Record<number, string> = {};
  erreur = signal<string | null>(null);
  succes = signal<string | null>(null);

  constructor() {
    this.charger();
  }

  charger(): void {
    this.api.get<DemandeRdv[]>('/gerant/demandes-rdv', { statut: this.filtre }).subscribe(list => {
      list.forEach(d => (this.dates[d.id] = toInputDateTime(d.dateSouhaitee)));
      this.demandes.set(list);
    });
  }

  accepter(d: DemandeRdv): void {
    this.action(this.api.post<{ message: string }>(`/gerant/demandes-rdv/${d.id}/accepter`, { dateRdv: this.dates[d.id] }));
  }

  refuser(d: DemandeRdv): void {
    if (confirm(`Refuser la demande de ${d.prenom} ${d.nom} ?`)) {
      this.action(this.api.post<{ message: string }>(`/gerant/demandes-rdv/${d.id}/refuser`));
    }
  }

  private action(obs: Observable<{ message: string }>): void {
    this.erreur.set(null);
    obs.subscribe({
      next: r => { this.succes.set(r.message); this.charger(); },
      error: e => this.erreur.set(errorMessage(e)),
    });
  }
}
