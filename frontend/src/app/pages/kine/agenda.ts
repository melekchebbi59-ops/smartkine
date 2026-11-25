import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Api, errorMessage } from '../../core/api';
import { Exercice, Seance, User } from '../../core/models';
import { Alert } from '../../shared/alert';
import { Badge } from '../../shared/badge';
import { SeanceForm } from './seance-form';

/** US-15 : agenda du kiné (semaine) — planifier, modifier, annuler, marquer effectuée. */
@Component({
  selector: 'app-kine-agenda',
  imports: [Alert, Badge, SeanceForm, RouterLink],
  template: `
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h1 class="page-title mb-0">Mon agenda</h1>
      <button class="btn btn-brand" (click)="edition.set('nouvelle')"><i class="bi bi-calendar-plus me-1"></i>Planifier</button>
    </div>
    <app-alert [error]="erreur()" [success]="succes()" />

    @if (edition() === 'nouvelle') {
      <app-seance-form [patients]="patients()" [exercices]="exercices()" (enregistre)="ok($event)" (annule)="edition.set(null)" />
    } @else if (edition(); as s) {
      @if (s !== 'nouvelle') {
        <app-seance-form [seance]="s" [exercices]="exercices()" (enregistre)="ok($event)" (annule)="edition.set(null)" />
      }
    }

    <div class="d-flex align-items-center gap-2 mb-3">
      <button class="btn btn-light" (click)="decaler(-7)" aria-label="Semaine précédente"><i class="bi bi-chevron-left"></i></button>
      <button class="btn btn-light" (click)="aujourdhui()">Aujourd'hui</button>
      <button class="btn btn-light" (click)="decaler(7)" aria-label="Semaine suivante"><i class="bi bi-chevron-right"></i></button>
      <strong class="ms-2">Semaine du {{ lundi().toLocaleDateString('fr-FR') }}</strong>
    </div>

    <div class="row g-3">
      @for (jour of jours(); track jour.date) {
        <div class="col-md-6 col-xl-4">
          <div class="card h-100" [class.border]="jour.aujourdhui" [class.border-success]="jour.aujourdhui">
            <div class="card-header text-capitalize">{{ jour.label }}</div>
            <div class="list-group list-group-flush">
              @for (s of jour.seances; track s.id) {
                <div class="list-group-item" [class.opacity-50]="s.statut === 'annulee'">
                  <div class="d-flex justify-content-between align-items-start">
                    <div>
                      <strong>{{ heure(s.dateHeure) }}</strong> · {{ s.duree }} min
                      <div><a [routerLink]="['/kine/patients', s.patient.id]">{{ s.patient.fullName }}</a></div>
                      @if (s.objectif) { <div class="small text-muted">{{ s.objectif }}</div> }
                      @if (s.exercices.length) { <div class="small text-muted"><i class="bi bi-list-check"></i> {{ s.exercices.length }} exercice(s)</div> }
                    </div>
                    <app-badge [statut]="s.statut" />
                  </div>
                  @if (s.statut === 'planifiee') {
                    <div class="mt-2 d-flex gap-1">
                      <button class="btn btn-sm btn-outline-success" (click)="effectuee(s)" title="Marquer effectuée"><i class="bi bi-check2-circle"></i></button>
                      <button class="btn btn-sm btn-light" (click)="edition.set(s)" title="Modifier"><i class="bi bi-pencil"></i></button>
                      <button class="btn btn-sm btn-outline-danger" (click)="annuler(s)" title="Annuler"><i class="bi bi-x-circle"></i></button>
                    </div>
                  }
                  @if (s.statut === 'report_demande' || s.statut === 'annulation_demandee') {
                    <a routerLink="/kine/demandes" class="small">Traiter la demande →</a>
                  }
                </div>
              } @empty {
                <div class="list-group-item small text-muted">Aucune séance</div>
              }
            </div>
          </div>
        </div>
      }
    </div>
  `,
})
export class KineAgenda {
  private api = inject(Api);

  lundi = signal(this.debutSemaine(new Date()));
  seances = signal<Seance[]>([]);
  patients = signal<User[]>([]);
  exercices = signal<Exercice[]>([]);
  edition = signal<Seance | 'nouvelle' | null>(null);
  erreur = signal<string | null>(null);
  succes = signal<string | null>(null);

  jours = computed(() => {
    const today = new Date().toDateString();
    return Array.from({ length: 6 }, (_, i) => {
      const d = new Date(this.lundi());
      d.setDate(d.getDate() + i);
      return {
        date: d.toDateString(),
        label: d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }),
        aujourdhui: d.toDateString() === today,
        seances: this.seances().filter(s => new Date(s.dateHeure).toDateString() === d.toDateString()),
      };
    });
  });

  constructor() {
    this.charger();
    this.api.get<User[]>('/kine/patients').subscribe(l => this.patients.set(l));
    this.api.get<Exercice[]>('/exercices').subscribe(l => this.exercices.set(l));
  }

  heure(iso: string): string {
    return new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  }

  private debutSemaine(d: Date): Date {
    const x = new Date(d);
    x.setHours(0, 0, 0, 0);
    x.setDate(x.getDate() - ((x.getDay() + 6) % 7));
    return x;
  }

  private iso(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }

  charger(): void {
    const fin = new Date(this.lundi());
    fin.setDate(fin.getDate() + 7);
    this.api.get<Seance[]>('/kine/seances', { debut: this.iso(this.lundi()), fin: this.iso(fin) }).subscribe(l => this.seances.set(l));
  }

  decaler(jours: number): void {
    const d = new Date(this.lundi());
    d.setDate(d.getDate() + jours);
    this.lundi.set(d);
    this.charger();
  }

  aujourdhui(): void {
    this.lundi.set(this.debutSemaine(new Date()));
    this.charger();
  }

  ok(message: string): void {
    this.succes.set(message);
    this.erreur.set(null);
    this.edition.set(null);
    this.charger();
  }

  effectuee(s: Seance): void {
    this.api.put<{ message: string }>(`/kine/seances/${s.id}`, { statut: 'effectuee' }).subscribe({
      next: () => this.ok('Séance marquée comme effectuée.'),
      error: e => this.erreur.set(errorMessage(e)),
    });
  }

  annuler(s: Seance): void {
    if (!confirm(`Annuler la séance de ${s.patient.fullName} ? Le patient sera notifié.`)) {
      return;
    }
    this.api.post<{ message: string }>(`/kine/seances/${s.id}/annuler`).subscribe({
      next: r => this.ok(r.message),
      error: e => this.erreur.set(errorMessage(e)),
    });
  }
}
