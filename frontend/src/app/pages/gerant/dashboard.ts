import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Api } from '../../core/api';
import { DemandeRdv, User } from '../../core/models';

/** Tableau de bord du gérant (Sprint 1 : actions en attente). */
@Component({
  selector: 'app-gerant-dashboard',
  imports: [RouterLink],
  template: `
    <h1 class="page-title">Tableau de bord</h1>
    <div class="row g-3">
      <div class="col-sm-6 col-xl-3">
        <a routerLink="/gerant/demandes" class="card stat-card p-3 text-decoration-none text-body h-100">
          <i class="bi bi-calendar-plus icon"></i>
          <div class="value">{{ demandes() }}</div>
          <div class="text-muted small">Demandes de RDV en attente</div>
        </a>
      </div>
      <div class="col-sm-6 col-xl-3">
        <a routerLink="/gerant/inscriptions" class="card stat-card p-3 text-decoration-none text-body h-100">
          <i class="bi bi-person-check icon"></i>
          <div class="value">{{ inscriptions() }}</div>
          <div class="text-muted small">Inscriptions à valider</div>
        </a>
      </div>
    </div>
  `,
})
export class GerantDashboard {
  private api = inject(Api);
  demandes = signal(0);
  inscriptions = signal(0);

  constructor() {
    this.api.get<DemandeRdv[]>('/gerant/demandes-rdv', { statut: 'en_attente' }).subscribe(l => this.demandes.set(l.length));
    this.api.get<User[]>('/gerant/inscriptions').subscribe(l => this.inscriptions.set(l.length));
  }
}
