import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Api, errorMessage } from '../../core/api';
import { Seance } from '../../core/models';
import { Alert } from '../../shared/alert';
import { Badge } from '../../shared/badge';
import { dateFr } from '../../shared/format';

/** US-06 planning, US-07 demande de report, US-08 demande d'annulation. */
@Component({
  selector: 'app-patient-planning',
  imports: [FormsModule, Alert, Badge],
  template: `
    <h1 class="page-title">Mon planning</h1>
    <app-alert [error]="erreur()" [success]="succes()" />

    <ul class="nav nav-pills mb-3">
      <li class="nav-item"><button class="nav-link" [class.active]="vue() === 'avenir'" (click)="vue.set('avenir')">À venir ({{ aVenir().length }})</button></li>
      <li class="nav-item"><button class="nav-link" [class.active]="vue() === 'passees'" (click)="vue.set('passees')">Historique</button></li>
    </ul>

    <div class="row g-3">
      @for (s of (vue() === 'avenir' ? aVenir() : passees()); track s.id) {
        <div class="col-md-6 col-xl-4">
          <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <div class="fw-bold text-capitalize">{{ jour(s.dateHeure) }}</div>
                <div class="fs-5 text-brand fw-semibold">{{ heure(s.dateHeure) }} <span class="small text-muted fw-normal">· {{ s.duree }} min</span></div>
              </div>
              <app-badge [statut]="s.statut" />
            </div>
            <div class="small"><i class="bi bi-person-badge me-1"></i>{{ s.kine?.fullName ?? '—' }}</div>
            @if (s.objectif) { <div class="small"><i class="bi bi-bullseye me-1"></i>{{ s.objectif }}</div> }
            @if (s.exercices.length) {
              <div class="small text-muted mt-1">{{ nomsExercices(s) }}</div>
            }
            @if (s.statut === 'report_demande') { <div class="small text-warning-emphasis mt-2">Report demandé au {{ dateFr(s.dateProposee) }} — en attente du kiné</div> }

            @if (s.statut === 'planifiee' && vue() === 'avenir') {
              @if (action()?.id === s.id) {
                <div class="border-top mt-3 pt-3">
                  @if (action()?.type === 'report') {
                    <label class="form-label small">Nouvelle date proposée *</label>
                    <input class="form-control form-control-sm mb-2" type="datetime-local" [min]="min" [(ngModel)]="dateProposee">
                  }
                  <label class="form-label small">Motif</label>
                  <input class="form-control form-control-sm mb-2" [(ngModel)]="motif" placeholder="Ex. : empêchement professionnel">
                  <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-brand" (click)="envoyer(s)">Envoyer la demande</button>
                    <button class="btn btn-sm btn-light" (click)="action.set(null)">Fermer</button>
                  </div>
                </div>
              } @else {
                <div class="d-flex gap-2 mt-3">
                  <button class="btn btn-sm btn-outline-primary flex-fill" (click)="ouvrir(s, 'report')"><i class="bi bi-arrow-left-right"></i> Reporter</button>
                  <button class="btn btn-sm btn-outline-danger flex-fill" (click)="ouvrir(s, 'annulation')"><i class="bi bi-x-circle"></i> Annuler</button>
                </div>
              }
            }
          </div>
        </div>
      } @empty {
        <div class="col-12"><div class="card empty"><i class="bi bi-calendar fs-2"></i><div>Aucune séance.</div></div></div>
      }
    </div>
  `,
})
export class PatientPlanning {
  private api = inject(Api);
  dateFr = dateFr;

  seances = signal<Seance[]>([]);
  vue = signal<'avenir' | 'passees'>('avenir');
  action = signal<{ id: number; type: 'report' | 'annulation' } | null>(null);
  dateProposee = '';
  motif = '';
  min = new Date().toISOString().slice(0, 16);
  erreur = signal<string | null>(null);
  succes = signal<string | null>(null);

  aVenir = computed(() => this.seances().filter(s => new Date(s.dateHeure) >= new Date() && s.statut !== 'annulee'));
  passees = computed(() => this.seances().filter(s => new Date(s.dateHeure) < new Date() || s.statut === 'annulee').reverse());

  constructor() {
    this.charger();
  }

  charger(): void {
    this.api.get<Seance[]>('/patient/seances').subscribe(l => this.seances.set(l));
  }

  jour(iso: string): string {
    return new Date(iso).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
  }

  heure(iso: string): string {
    return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  }

  nomsExercices(s: Seance): string {
    return s.exercices.map(e => e.nom).join(', ');
  }

  ouvrir(s: Seance, type: 'report' | 'annulation'): void {
    this.dateProposee = '';
    this.motif = '';
    this.action.set({ id: s.id, type });
  }

  envoyer(s: Seance): void {
    const type = this.action()?.type;
    this.erreur.set(null);
    const body = type === 'report' ? { dateProposee: this.dateProposee, motif: this.motif } : { motif: this.motif };
    this.api.post<{ message: string }>(`/patient/seances/${s.id}/${type}`, body).subscribe({
      next: r => { this.succes.set(r.message); this.action.set(null); this.charger(); },
      error: e => this.erreur.set(errorMessage(e)),
    });
  }
}
