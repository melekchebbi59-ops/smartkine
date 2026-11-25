import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Api, errorMessage } from '../../core/api';
import { User } from '../../core/models';
import { Alert } from '../../shared/alert';
import { Badge } from '../../shared/badge';
import { dateFr } from '../../shared/format';

/** US-23 : validation des inscriptions patients. */
@Component({
  selector: 'app-gerant-inscriptions',
  imports: [FormsModule, Alert, Badge],
  template: `
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h1 class="page-title mb-0">Inscriptions des patients</h1>
      <select class="form-select w-auto" [(ngModel)]="filtre" (change)="charger()">
        <option value="en_attente">En attente</option>
        <option value="valide">Validées</option>
        <option value="refuse">Refusées</option>
      </select>
    </div>
    <app-alert [error]="erreur()" [success]="succes()" />

    <div class="card">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Patient</th><th class="d-none d-md-table-cell">Contact</th><th class="d-none d-lg-table-cell">Pathologie</th><th>Statut</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
            @for (p of patients(); track p.id) {
              <tr>
                <td><strong>{{ p.fullName }}</strong><div class="small text-muted">inscrit le {{ dateFr(p.createdAt, false) }}</div></td>
                <td class="d-none d-md-table-cell small">{{ p.email }}<br>{{ p.phone }}</td>
                <td class="d-none d-lg-table-cell small">{{ p.typeTraitement ?? '—' }}</td>
                <td><app-badge [statut]="p.statutInscription ?? ''" kind="demande" /></td>
                <td class="text-end text-nowrap">
                  @if (p.statutInscription !== 'valide') {
                    <button class="btn btn-sm btn-success me-1" (click)="traiter(p, 'valider')" title="Valider"><i class="bi bi-check-lg"></i><span class="d-none d-md-inline"> Valider</span></button>
                  }
                  @if (p.statutInscription === 'en_attente') {
                    <button class="btn btn-sm btn-outline-danger" (click)="traiter(p, 'refuser')" title="Refuser"><i class="bi bi-x-lg"></i></button>
                  }
                </td>
              </tr>
            } @empty {
              <tr><td colspan="5" class="empty">Aucune inscription.</td></tr>
            }
          </tbody>
        </table>
      </div>
    </div>
  `,
})
export class GerantInscriptions {
  private api = inject(Api);
  dateFr = dateFr;

  filtre = 'en_attente';
  patients = signal<User[]>([]);
  erreur = signal<string | null>(null);
  succes = signal<string | null>(null);

  constructor() {
    this.charger();
  }

  charger(): void {
    this.api.get<User[]>('/gerant/inscriptions', { statut: this.filtre }).subscribe(l => this.patients.set(l));
  }

  traiter(p: User, action: 'valider' | 'refuser'): void {
    this.erreur.set(null);
    this.api.post<{ message: string }>(`/gerant/inscriptions/${p.id}/${action}`).subscribe({
      next: r => { this.succes.set(r.message + ' Un e-mail a été envoyé au patient.'); this.charger(); },
      error: e => this.erreur.set(errorMessage(e)),
    });
  }
}
