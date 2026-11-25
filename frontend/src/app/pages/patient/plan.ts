import { Component, inject, signal } from '@angular/core';
import { Api } from '../../core/api';
import { BACKEND_URL } from '../../core/config';
import { Plan } from '../../core/models';
import { dateFr } from '../../shared/format';

/** US-09 : le patient consulte son plan de rééducation et les exercices prescrits. */
@Component({
  selector: 'app-patient-plan',
  template: `
    <h1 class="page-title">Mon plan de rééducation</h1>
    @for (p of plans(); track p.id) {
      <div class="card p-4 mb-3">
        <h4 class="fw-bold mb-1">{{ p.titre }}</h4>
        <div class="small text-muted mb-2">Prescrit par {{ p.kine?.fullName ?? '—' }} le {{ dateFr(p.dateCreation, false) }}</div>
        @if (p.objectifs) { <p class="mb-3"><i class="bi bi-bullseye text-brand me-1"></i>{{ p.objectifs }}</p> }
        <div class="row g-3">
          @for (e of p.exercices; track e.id; let i = $index) {
            <div class="col-sm-6 col-lg-4">
              <div class="card h-100 border">
                @if (e.image) { <img [src]="backend + e.image" class="exo-img" [alt]="e.nom"> }
                <div class="p-3">
                  <div class="small text-brand fw-semibold">Exercice {{ i + 1 }} · {{ e.duree }} min</div>
                  <h6 class="fw-bold">{{ e.nom }}</h6>
                  <p class="small mb-0">{{ e.objectif }}</p>
                </div>
              </div>
            </div>
          }
        </div>
        @if (p.seances?.length) {
          <div class="small text-muted mt-3"><i class="bi bi-calendar-check me-1"></i>{{ p.seances?.length }} séance(s) liée(s) à ce plan</div>
        }
      </div>
    } @empty {
      <div class="card empty"><i class="bi bi-journal-medical fs-2"></i><div>Votre kiné n'a pas encore créé de plan de rééducation.</div></div>
    }
  `,
})
export class PatientPlan {
  private api = inject(Api);
  dateFr = dateFr;
  backend = BACKEND_URL;
  plans = signal<Plan[]>([]);

  constructor() {
    this.api.get<Plan[]>('/patient/plans').subscribe(l => this.plans.set(l));
  }
}
