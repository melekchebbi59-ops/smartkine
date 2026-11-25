import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Api } from '../../core/api';
import { Seance } from '../../core/models';
import { BarChart, BarDatum } from '../../shared/bar-chart';
import { Badge } from '../../shared/badge';
import { dt } from '../../shared/format';

interface Stats {
  seancesJour: number;
  seancesSemaine: number;
  patientsActifs: number;
  demandesEnAttente: number;
  inscriptionsEnAttente: number;
  patientsSansKine: number;
  recetteJour: string;
  recetteMois: string;
  resteAEncaisser: string;
  patientsParKine: { kine: string; patients: number; actif: boolean }[];
  recettesJournalieres: { date: string; montant: number }[];
  recettesMensuelles: { mois: string; montant: number }[];
}

/** Tableau de bord du gérant : US-27 activité globale, US-28 statistiques. */
@Component({
  selector: 'app-gerant-dashboard',
  imports: [RouterLink, BarChart, Badge],
  template: `
    <h1 class="page-title">Tableau de bord</h1>

    @if (stats(); as s) {
      <!-- Actions en attente -->
      <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><a routerLink="/gerant/demandes" class="card stat-card p-3 text-decoration-none text-body h-100"><i class="bi bi-calendar-plus icon"></i><div class="value">{{ s.demandesEnAttente }}</div><div class="text-muted small">Demandes de RDV en attente</div></a></div>
        <div class="col-6 col-xl-3"><a routerLink="/gerant/inscriptions" class="card stat-card p-3 text-decoration-none text-body h-100"><i class="bi bi-person-check icon"></i><div class="value">{{ s.inscriptionsEnAttente }}</div><div class="text-muted small">Inscriptions à valider</div></a></div>
        <div class="col-6 col-xl-3"><a routerLink="/gerant/patients" class="card stat-card p-3 text-decoration-none text-body h-100"><i class="bi bi-diagram-3 icon"></i><div class="value">{{ s.patientsSansKine }}</div><div class="text-muted small">Patients sans kiné</div></a></div>
        <div class="col-6 col-xl-3"><div class="card stat-card p-3 h-100"><i class="bi bi-people icon"></i><div class="value">{{ s.patientsActifs }}</div><div class="text-muted small">Patients actifs</div></div></div>
      </div>

      <!-- Activité & recettes -->
      <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><div class="card stat-card p-3 h-100"><i class="bi bi-calendar-day icon"></i><div class="value">{{ s.seancesJour }}</div><div class="text-muted small">Séances aujourd'hui</div></div></div>
        <div class="col-6 col-xl-3"><div class="card stat-card p-3 h-100"><i class="bi bi-calendar-week icon"></i><div class="value">{{ s.seancesSemaine }}</div><div class="text-muted small">Séances cette semaine</div></div></div>
        <div class="col-6 col-xl-3"><div class="card stat-card p-3 h-100"><i class="bi bi-cash-coin icon"></i><div class="value">{{ dt(s.recetteJour) }}</div><div class="text-muted small">Recette du jour · {{ dt(s.recetteMois) }} ce mois</div></div></div>
        <div class="col-6 col-xl-3"><a routerLink="/gerant/factures" class="card stat-card p-3 text-decoration-none text-body h-100"><i class="bi bi-hourglass-split icon"></i><div class="value">{{ dt(s.resteAEncaisser) }}</div><div class="text-muted small">Reste à encaisser</div></a></div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-lg-7">
          <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h6 class="fw-semibold mb-0">Recettes {{ vue() === 'mois' ? 'mensuelles (12 mois)' : 'journalières (30 jours)' }}</h6>
              <div class="btn-group btn-group-sm">
                <button class="btn" [class.btn-brand]="vue() === 'jour'" [class.btn-light]="vue() !== 'jour'" (click)="vue.set('jour')">Jours</button>
                <button class="btn" [class.btn-brand]="vue() === 'mois'" [class.btn-light]="vue() !== 'mois'" (click)="vue.set('mois')">Mois</button>
              </div>
            </div>
            <app-bar-chart [data]="vue() === 'mois' ? mensuelles() : journalieres()" [labelEvery]="vue() === 'mois' ? 1 : 5" [format]="formatDt" />
          </div>
        </div>
        <div class="col-lg-5">
          <div class="card p-3 h-100">
            <h6 class="fw-semibold mb-3">Patients par kiné</h6>
            @for (k of s.patientsParKine; track k.kine) {
              <div class="mb-2" [class.opacity-50]="!k.actif">
                <div class="d-flex justify-content-between small"><span>{{ k.kine }}</span><strong>{{ k.patients }}</strong></div>
                <div class="progress" style="height: 8px"><div class="progress-bar bg-brand" [style.width.%]="maxPatients() ? (k.patients / maxPatients()) * 100 : 0"></div></div>
              </div>
            } @empty { <div class="text-muted small">Aucun kiné.</div> }
          </div>
        </div>
      </div>
    }

    <!-- US-27 : activité du jour / semaine -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Activité du centre</span>
        <div class="btn-group btn-group-sm">
          <button class="btn" [class.btn-brand]="periode() === 'jour'" [class.btn-light]="periode() !== 'jour'" (click)="periode.set('jour')">Aujourd'hui</button>
          <button class="btn" [class.btn-brand]="periode() === 'semaine'" [class.btn-light]="periode() !== 'semaine'" (click)="periode.set('semaine')">Semaine</button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Date</th><th>Patient</th><th class="d-none d-md-table-cell">Kiné</th><th>Statut</th></tr></thead>
          <tbody>
            @for (x of (periode() === 'jour' ? activite().jour : activite().semaine); track x.id) {
              <tr>
                <td class="text-nowrap">{{ dateCourte(x.dateHeure) }}</td>
                <td>{{ x.patient.fullName }}</td>
                <td class="d-none d-md-table-cell">{{ x.kine?.fullName ?? '—' }}</td>
                <td><app-badge [statut]="x.statut" /></td>
              </tr>
            } @empty { <tr><td colspan="4" class="empty">Aucune séance sur la période.</td></tr> }
          </tbody>
        </table>
      </div>
    </div>
  `,
})
export class GerantDashboard {
  private api = inject(Api);
  dt = dt;
  formatDt = (v: number) => dt(v);

  stats = signal<Stats | null>(null);
  activite = signal<{ jour: Seance[]; semaine: Seance[] }>({ jour: [], semaine: [] });
  vue = signal<'jour' | 'mois'>('mois');
  periode = signal<'jour' | 'semaine'>('jour');

  maxPatients = computed(() => Math.max(0, ...(this.stats()?.patientsParKine ?? []).map(k => k.patients)));
  mensuelles = computed<BarDatum[]>(() => (this.stats()?.recettesMensuelles ?? []).map(m => ({
    label: new Date(m.mois + '-01').toLocaleDateString('fr-FR', { month: 'short' }), value: m.montant,
  })));
  journalieres = computed<BarDatum[]>(() => (this.stats()?.recettesJournalieres ?? []).map(j => ({
    label: new Date(j.date).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }), value: j.montant,
  })));

  constructor() {
    this.api.get<Stats>('/gerant/statistiques').subscribe(s => this.stats.set(s));
    this.api.get<{ jour: Seance[]; semaine: Seance[] }>('/gerant/activite').subscribe(a => this.activite.set(a));
  }

  dateCourte(iso: string): string {
    return new Date(iso).toLocaleString('fr-FR', { weekday: 'short', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
  }
}
