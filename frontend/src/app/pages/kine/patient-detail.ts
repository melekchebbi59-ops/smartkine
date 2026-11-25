import { Component, inject, input, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Api, errorMessage } from '../../core/api';
import { DossierMedical, Exercice, Plan, Seance, User } from '../../core/models';
import { Alert } from '../../shared/alert';
import { Badge } from '../../shared/badge';
import { ExercicePicker } from '../../shared/exercice-picker';
import { dateFr } from '../../shared/format';
import { SeanceForm } from './seance-form';

type Fiche = User & { dossier: DossierMedical | null; seances: Seance[]; plans: Plan[] };

/** Fiche patient du kiné : dossier médical (US-17), séances (US-15), plans de rééducation (US-18). */
@Component({
  selector: 'app-kine-patient-detail',
  imports: [FormsModule, RouterLink, Alert, Badge, ExercicePicker, SeanceForm],
  template: `
    <a routerLink="/kine/patients" class="small"><i class="bi bi-arrow-left"></i> Mes patients</a>
    @if (fiche(); as p) {
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 my-2">
        <div>
          <h1 class="page-title mb-0">{{ p.fullName }}</h1>
          <div class="small text-muted">
            {{ p.dateNaissance ? 'Né(e) le ' + dateFr(p.dateNaissance, false) + ' · ' : '' }}{{ p.phone }} · {{ p.email }}
            @if (p.medecinTraitant) { · Médecin : {{ p.medecinTraitant }} }
          </div>
        </div>
      </div>
      <app-alert [error]="erreur()" [success]="succes()" />

      <ul class="nav nav-pills mb-3">
        <li class="nav-item"><button class="nav-link" [class.active]="onglet() === 'dossier'" (click)="onglet.set('dossier')"><i class="bi bi-clipboard2-pulse me-1"></i>Dossier</button></li>
        <li class="nav-item"><button class="nav-link" [class.active]="onglet() === 'seances'" (click)="onglet.set('seances')"><i class="bi bi-calendar-week me-1"></i>Séances ({{ p.seances.length }})</button></li>
        <li class="nav-item"><button class="nav-link" [class.active]="onglet() === 'plans'" (click)="onglet.set('plans')"><i class="bi bi-journal-medical me-1"></i>Plans ({{ p.plans.length }})</button></li>
      </ul>

      @switch (onglet()) {
        @case ('dossier') {
          <div class="card p-4">
            <div class="row g-3">
              <div class="col-12"><label class="form-label">Description de l'état</label><textarea class="form-control" rows="3" [(ngModel)]="dossier.descriptionEtat"></textarea></div>
              <div class="col-4"><label class="form-label">Tension (TA)</label><input class="form-control" [(ngModel)]="dossier.tensionArterielle" placeholder="12/8"></div>
              <div class="col-4"><label class="form-label">Poids (kg)</label><input class="form-control" type="number" step="0.1" [(ngModel)]="dossier.poids"></div>
              <div class="col-4"><label class="form-label">Glycémie (g/L)</label><input class="form-control" type="number" step="0.01" [(ngModel)]="dossier.glycemie"></div>
              <div class="col-12"><label class="form-label">Antécédents / autres maladies</label><textarea class="form-control" rows="2" [(ngModel)]="dossier.autresMaladies"></textarea></div>
            </div>
            <div class="d-flex align-items-center gap-3 mt-3">
              <button class="btn btn-brand" (click)="sauverDossier()">Enregistrer le dossier</button>
              @if (p.dossier) { <span class="small text-muted">Dernière mise à jour : {{ dateFr(p.dossier.updatedAt) }}</span> }
            </div>
          </div>
        }
        @case ('seances') {
          @if (formSeance()) {
            <app-seance-form [patientFixe]="p.id" [patients]="[p]" [exercices]="exercices()" (enregistre)="ok($event)" (annule)="formSeance.set(false)" />
          } @else {
            <button class="btn btn-brand mb-3" (click)="formSeance.set(true)"><i class="bi bi-calendar-plus me-1"></i>Planifier des séances</button>
          }
          <div class="card">
            <div class="table-responsive">
              <table class="table mb-0">
                <thead><tr><th>Date</th><th class="d-none d-md-table-cell">Objectif</th><th>Exercices</th><th>Statut</th></tr></thead>
                <tbody>
                  @for (s of p.seances; track s.id) {
                    <tr><td>{{ dateFr(s.dateHeure) }}</td><td class="d-none d-md-table-cell small">{{ s.objectif ?? '—' }}</td><td class="small">{{ s.exercices.length }}</td><td><app-badge [statut]="s.statut" /></td></tr>
                  } @empty { <tr><td colspan="4" class="empty">Aucune séance.</td></tr> }
                </tbody>
              </table>
            </div>
          </div>
        }
        @case ('plans') {
          <div class="card p-4 mb-3">
            <h5 class="mb-3">{{ planEdit ? 'Modifier le plan' : 'Nouveau plan de rééducation' }}</h5>
            <div class="row g-3">
              <div class="col-md-6"><label class="form-label">Titre *</label><input class="form-control" [(ngModel)]="planForm.titre" placeholder="Ex. : Rééducation post-entorse"></div>
              <div class="col-md-6"><label class="form-label">Objectifs</label><input class="form-control" [(ngModel)]="planForm.objectifs"></div>
              <div class="col-12"><label class="form-label">Exercices de la banque *</label><app-exercice-picker [exercices]="exercices()" [(selected)]="planExos" /></div>
            </div>
            <div class="d-flex gap-2 mt-3">
              <button class="btn btn-brand" (click)="sauverPlan()">{{ planEdit ? 'Enregistrer' : 'Créer le plan' }}</button>
              @if (planEdit) { <button class="btn btn-light" (click)="resetPlan()">Annuler</button> }
            </div>
          </div>
          @for (pl of p.plans; track pl.id) {
            <div class="card p-3 mb-2">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <h6 class="fw-bold mb-1">{{ pl.titre }}</h6>
                  <div class="small text-muted">Créé le {{ dateFr(pl.dateCreation, false) }}{{ pl.objectifs ? ' · ' + pl.objectifs : '' }}</div>
                </div>
                <div class="d-flex gap-1">
                  <button class="btn btn-sm btn-light" (click)="editerPlan(pl)" title="Modifier"><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-sm btn-outline-danger" (click)="supprimerPlan(pl)" title="Supprimer"><i class="bi bi-trash"></i></button>
                </div>
              </div>
              <div class="d-flex flex-wrap gap-1 mt-2">
                @for (e of pl.exercices; track e.id) { <span class="badge bg-brand-light text-brand">{{ e.nom }} · {{ e.duree }}'</span> }
              </div>
            </div>
          }
        }
      }
    }
  `,
})
export class KinePatientDetail implements OnInit {
  private api = inject(Api);
  dateFr = dateFr;

  /** Paramètre :id de la route (withComponentInputBinding). */
  id = input.required<string>();

  fiche = signal<Fiche | null>(null);
  exercices = signal<Exercice[]>([]);
  onglet = signal<'dossier' | 'seances' | 'plans'>('dossier');
  formSeance = signal(false);
  erreur = signal<string | null>(null);
  succes = signal<string | null>(null);

  dossier = { descriptionEtat: '', tensionArterielle: '', poids: '', glycemie: '', autresMaladies: '' };
  planForm = { titre: '', objectifs: '' };
  planExos = signal<number[]>([]);
  planEdit: Plan | null = null;

  ngOnInit(): void {
    this.charger();
    this.api.get<Exercice[]>('/exercices').subscribe(l => this.exercices.set(l));
  }

  charger(): void {
    this.api.get<Fiche>(`/kine/patients/${this.id()}`).subscribe({
      next: f => {
        this.fiche.set(f);
        const d = f.dossier;
        this.dossier = {
          descriptionEtat: d?.descriptionEtat ?? '', tensionArterielle: d?.tensionArterielle ?? '',
          poids: d?.poids ?? '', glycemie: d?.glycemie ?? '', autresMaladies: d?.autresMaladies ?? '',
        };
      },
      error: e => this.erreur.set(errorMessage(e)),
    });
  }

  ok(message: string): void {
    this.succes.set(message);
    this.erreur.set(null);
    this.formSeance.set(false);
    this.charger();
  }

  sauverDossier(): void {
    this.api.put<{ message: string }>(`/kine/patients/${this.id()}/dossier`, this.dossier).subscribe({
      next: r => this.ok(r.message),
      error: e => this.erreur.set(errorMessage(e)),
    });
  }

  editerPlan(pl: Plan): void {
    this.planEdit = pl;
    this.planForm = { titre: pl.titre, objectifs: pl.objectifs ?? '' };
    this.planExos.set(pl.exercices.map(e => e.id));
  }

  resetPlan(): void {
    this.planEdit = null;
    this.planForm = { titre: '', objectifs: '' };
    this.planExos.set([]);
  }

  sauverPlan(): void {
    const body = { ...this.planForm, exerciceIds: this.planExos(), patientId: Number(this.id()) };
    const req = this.planEdit
      ? this.api.put<{ message: string }>(`/kine/plans/${this.planEdit.id}`, body)
      : this.api.post<{ message: string }>('/kine/plans', body);
    req.subscribe({
      next: r => { this.resetPlan(); this.ok(r.message); },
      error: e => this.erreur.set(errorMessage(e)),
    });
  }

  supprimerPlan(pl: Plan): void {
    if (confirm(`Supprimer le plan « ${pl.titre} » ?`)) {
      this.api.delete<{ message: string }>(`/kine/plans/${pl.id}`).subscribe({
        next: r => this.ok(r.message),
        error: e => this.erreur.set(errorMessage(e)),
      });
    }
  }
}
