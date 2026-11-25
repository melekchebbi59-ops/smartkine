import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Api, errorMessage } from '../../core/api';
import { DossierMedical, User } from '../../core/models';
import { Alert } from '../../shared/alert';
import { dateFr } from '../../shared/format';

/** US-24 : affectation des patients validés aux kinés (+ consultation du dossier). */
@Component({
  selector: 'app-gerant-patients',
  imports: [FormsModule, Alert],
  template: `
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h1 class="page-title mb-0">Patients & affectations</h1>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="sk" [ngModel]="sansKine()" (ngModelChange)="sansKine.set($event)">
        <label class="form-check-label" for="sk">Seulement les patients sans kiné ({{ nbSansKine() }})</label>
      </div>
    </div>
    <app-alert [error]="erreur()" [success]="succes()" />

    <div class="card">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Patient</th><th class="d-none d-md-table-cell">Pathologie</th><th style="min-width: 220px">Kiné affecté</th><th></th></tr></thead>
          <tbody>
            @for (p of affiches(); track p.id) {
              <tr>
                <td><strong>{{ p.fullName }}</strong><div class="small text-muted">{{ p.phone }}</div></td>
                <td class="d-none d-md-table-cell small">{{ p.typeTraitement ?? '—' }}</td>
                <td>
                  <select class="form-select form-select-sm" [ngModel]="p.kine?.id ?? null" (ngModelChange)="affecter(p, $event)">
                    <option [ngValue]="null">— Non affecté —</option>
                    @for (k of kines(); track k.id) {
                      <option [ngValue]="k.id">{{ k.fullName }}{{ k.specialite ? ' (' + k.specialite + ')' : '' }} · {{ k.nbPatients }} pat.</option>
                    }
                  </select>
                </td>
                <td class="text-end"><button class="btn btn-sm btn-light" (click)="voir(p)" title="Dossier médical"><i class="bi bi-folder2-open"></i></button></td>
              </tr>
            } @empty {
              <tr><td colspan="4" class="empty">Aucun patient validé.</td></tr>
            }
          </tbody>
        </table>
      </div>
    </div>

    @if (fiche(); as f) {
      <div class="card p-4 mt-3">
        <div class="d-flex justify-content-between"><h5>Dossier médical — {{ f.fullName }}</h5><button class="btn-close" (click)="fiche.set(null)" aria-label="Fermer"></button></div>
        @if (f.dossier; as d) {
          <div class="row g-2 small">
            <div class="col-md-6"><strong>État :</strong> {{ d.descriptionEtat ?? '—' }}</div>
            <div class="col-md-6"><strong>Antécédents :</strong> {{ d.autresMaladies ?? '—' }}</div>
            <div class="col-4"><strong>TA :</strong> {{ d.tensionArterielle ?? '—' }}</div>
            <div class="col-4"><strong>Poids :</strong> {{ d.poids ?? '—' }} kg</div>
            <div class="col-4"><strong>Glycémie :</strong> {{ d.glycemie ?? '—' }} g/L</div>
            <div class="col-12 text-muted">Mis à jour le {{ dateFr(d.updatedAt) }}</div>
          </div>
        } @else {
          <p class="text-muted mb-0">Le dossier n'a pas encore été rempli par le kiné.</p>
        }
      </div>
    }
  `,
})
export class GerantPatients {
  private api = inject(Api);
  dateFr = dateFr;

  patients = signal<User[]>([]);
  kines = signal<User[]>([]);
  sansKine = signal(false);
  fiche = signal<(User & { dossier: DossierMedical | null }) | null>(null);
  erreur = signal<string | null>(null);
  succes = signal<string | null>(null);
  affiches = computed(() => this.sansKine() ? this.patients().filter(p => !p.kine) : this.patients());
  nbSansKine = computed(() => this.patients().filter(p => !p.kine).length);

  constructor() {
    this.charger();
  }

  charger(): void {
    this.api.get<User[]>('/gerant/patients').subscribe(l => this.patients.set(l));
    this.api.get<User[]>('/gerant/kines').subscribe(l => this.kines.set(l));
  }

  affecter(p: User, kineId: number | null): void {
    this.erreur.set(null);
    this.api.post<{ message: string }>(`/gerant/patients/${p.id}/affecter`, { kineId }).subscribe({
      next: r => { this.succes.set(r.message); this.charger(); },
      error: e => this.erreur.set(errorMessage(e)),
    });
  }

  voir(p: User): void {
    this.api.get<User & { dossier: DossierMedical | null }>(`/gerant/patients/${p.id}`).subscribe(f => this.fiche.set(f));
  }
}
