import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Api } from '../../core/api';
import { Seance, User } from '../../core/models';
import { Auth } from '../../services/auth';
import { dateFr } from '../../shared/format';

/** Accueil patient : accès rapide à la prochaine séance. */
@Component({
  selector: 'app-patient-accueil',
  imports: [RouterLink],
  template: `
    <h1 class="page-title">Bonjour {{ auth.user()?.firstName }} 👋</h1>
    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card p-4 h-100 bg-brand text-white">
          <div class="small text-uppercase opacity-75">Prochaine séance</div>
          @if (prochaine(); as s) {
            <div class="fs-4 fw-bold text-capitalize">{{ dateFr(s.dateHeure) }}</div>
            <div class="opacity-75">avec {{ s.kine?.fullName }} · {{ s.duree }} min</div>
            @if (s.objectif) { <div class="mt-2">{{ s.objectif }}</div> }
          } @else {
            <div class="fs-5 fw-semibold mt-1">Aucune séance planifiée</div>
          }
          <a routerLink="/patient/planning" class="btn btn-light btn-sm mt-3 align-self-start text-brand">Voir mon planning</a>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card p-4 h-100">
          <div class="small text-uppercase text-muted">Mon kiné</div>
          <div class="fs-5 fw-semibold">{{ moi()?.kine?.fullName ?? 'Pas encore affecté' }}</div>
          <div class="d-flex flex-wrap gap-2 mt-3">
            <a routerLink="/patient/plan" class="btn btn-outline-primary btn-sm"><i class="bi bi-journal-medical me-1"></i>Mon plan de rééducation</a>
          </div>
        </div>
      </div>
    </div>
  `,
})
export class PatientAccueil {
  auth = inject(Auth);
  private api = inject(Api);
  dateFr = dateFr;

  seances = signal<Seance[]>([]);
  moi = signal<User | null>(null);
  prochaine = computed(() => this.seances().find(s => new Date(s.dateHeure) >= new Date() && ['planifiee', 'report_demande'].includes(s.statut)) ?? null);

  constructor() {
    this.api.get<Seance[]>('/patient/seances').subscribe(l => this.seances.set(l));
    this.api.get<User>('/me').subscribe(u => this.moi.set(u));
  }
}
