import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Api } from '../../core/api';
import { Seance, User } from '../../core/models';
import { Auth } from '../../services/auth';
import { Badge } from '../../shared/badge';

/** Accueil du kiné : séances du jour + demandes en attente. */
@Component({
  selector: 'app-kine-accueil',
  imports: [RouterLink, Badge],
  template: `
    <h1 class="page-title">Bonjour {{ auth.user()?.firstName }} 👋</h1>
    <div class="row g-3 mb-3">
      <div class="col-6 col-lg-3"><div class="card stat-card p-3"><i class="bi bi-calendar-day icon"></i><div class="value">{{ duJour().length }}</div><div class="small text-muted">Séances aujourd'hui</div></div></div>
      <div class="col-6 col-lg-3"><a routerLink="/kine/demandes" class="card stat-card p-3 text-decoration-none text-body"><i class="bi bi-arrow-left-right icon"></i><div class="value">{{ demandes() }}</div><div class="small text-muted">Demandes en attente</div></a></div>
      <div class="col-6 col-lg-3"><a routerLink="/kine/patients" class="card stat-card p-3 text-decoration-none text-body"><i class="bi bi-people icon"></i><div class="value">{{ patients() }}</div><div class="small text-muted">Patients suivis</div></a></div>
      <div class="col-6 col-lg-3"><div class="card stat-card p-3"><i class="bi bi-calendar-week icon"></i><div class="value">{{ semaine().length }}</div><div class="small text-muted">Séances cette semaine</div></div></div>
    </div>
    <div class="card">
      <div class="card-header d-flex justify-content-between"><span>Aujourd'hui</span><a routerLink="/kine/agenda" class="small">Agenda complet →</a></div>
      <div class="list-group list-group-flush">
        @for (s of duJour(); track s.id) {
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <div><strong>{{ heure(s.dateHeure) }}</strong> — <a [routerLink]="['/kine/patients', s.patient.id]">{{ s.patient.fullName }}</a><div class="small text-muted">{{ s.objectif }}</div></div>
            <app-badge [statut]="s.statut" />
          </div>
        } @empty { <div class="list-group-item empty">Pas de séance aujourd'hui.</div> }
      </div>
    </div>
  `,
})
export class KineAccueil {
  auth = inject(Auth);
  private api = inject(Api);

  semaine = signal<Seance[]>([]);
  demandes = signal(0);
  patients = signal(0);
  duJour = computed(() => this.semaine().filter(s => new Date(s.dateHeure).toDateString() === new Date().toDateString()));

  constructor() {
    this.api.get<Seance[]>('/kine/seances').subscribe(l => this.semaine.set(l));
    this.api.get<Seance[]>('/kine/seances/demandes').subscribe(l => this.demandes.set(l.length));
    this.api.get<User[]>('/kine/patients').subscribe(l => this.patients.set(l.length));
  }

  heure(iso: string): string {
    return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  }
}
