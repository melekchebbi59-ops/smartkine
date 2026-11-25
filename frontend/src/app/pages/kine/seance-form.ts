import { Component, inject, input, OnInit, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Api, errorMessage } from '../../core/api';
import { Exercice, Plan, Seance, User } from '../../core/models';
import { Alert } from '../../shared/alert';
import { ExercicePicker } from '../../shared/exercice-picker';
import { toInputDateTime } from '../../shared/format';

/** Formulaire de planification / modification d'une séance (US-15). */
@Component({
  selector: 'app-seance-form',
  imports: [FormsModule, Alert, ExercicePicker],
  template: `
    <div class="card p-4 mb-3 border border-2 border-success-subtle">
      <h5 class="mb-3">{{ seance() ? 'Modifier la séance' : 'Planifier une séance' }}</h5>
      <app-alert [error]="erreur()" />
      <div class="row g-3">
        @if (!seance()) {
          <div class="col-md-6">
            <label class="form-label">Patient *</label>
            <select class="form-select" [(ngModel)]="patientId" (ngModelChange)="chargerPlans()">
              <option [ngValue]="0" disabled>Choisir...</option>
              @for (p of patients(); track p.id) { <option [ngValue]="p.id">{{ p.fullName }}</option> }
            </select>
          </div>
        }
        <div class="col-md-6"><label class="form-label">Date et heure *</label><input class="form-control" type="datetime-local" [(ngModel)]="dateHeure"></div>
        <div class="col-6 col-md-3"><label class="form-label">Durée (min)</label><input class="form-control" type="number" min="15" step="5" [(ngModel)]="duree"></div>
        @if (!seance()) {
          <div class="col-6 col-md-3"><label class="form-label">Nombre de séances</label><input class="form-control" type="number" min="1" max="20" [(ngModel)]="nombre"></div>
          @if (nombre > 1) {
            <div class="col-6 col-md-3"><label class="form-label">Tous les (jours)</label><input class="form-control" type="number" min="1" max="30" [(ngModel)]="intervalleJours"></div>
          }
          <div class="col-md-6">
            <label class="form-label">Plan de rééducation</label>
            <select class="form-select" [(ngModel)]="planId" (ngModelChange)="appliquerPlan()">
              <option [ngValue]="null">— Aucun —</option>
              @for (pl of plans(); track pl.id) { <option [ngValue]="pl.id">{{ pl.titre }}</option> }
            </select>
          </div>
        }
        <div class="col-12"><label class="form-label">Objectif de la séance</label><input class="form-control" [(ngModel)]="objectif" placeholder="Ex. : gagner 10° de flexion"></div>
        <div class="col-12">
          <label class="form-label">Exercices associés</label>
          <app-exercice-picker [exercices]="exercices()" [(selected)]="exerciceIds" />
        </div>
      </div>
      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-brand" (click)="enregistrer()" [disabled]="envoi()">Enregistrer</button>
        <button class="btn btn-light" (click)="annule.emit()">Annuler</button>
      </div>
    </div>
  `,
})
export class SeanceForm implements OnInit {
  private api = inject(Api);

  patients = input<User[]>([]);
  exercices = input<Exercice[]>([]);
  seance = input<Seance | null>(null);
  patientFixe = input<number | null>(null);
  enregistre = output<string>();
  annule = output<void>();

  patientId = 0;
  dateHeure = '';
  duree = 45;
  nombre = 1;
  intervalleJours = 7;
  planId: number | null = null;
  objectif = '';
  exerciceIds = signal<number[]>([]);
  plans = signal<Plan[]>([]);
  envoi = signal(false);
  erreur = signal<string | null>(null);

  ngOnInit(): void {
    const s = this.seance();
    if (s) {
      this.dateHeure = toInputDateTime(s.dateHeure);
      this.duree = s.duree;
      this.objectif = s.objectif ?? '';
      this.exerciceIds.set(s.exercices.map(e => e.id));
    } else if (this.patientFixe()) {
      this.patientId = this.patientFixe()!;
      this.chargerPlans();
    }
  }

  chargerPlans(): void {
    this.planId = null;
    this.api.get<Plan[]>('/kine/plans', { patientId: this.patientId }).subscribe(l => this.plans.set(l));
  }

  /** Choisir un plan pré-coche ses exercices. */
  appliquerPlan(): void {
    const plan = this.plans().find(p => p.id === this.planId);
    if (plan) {
      this.exerciceIds.set(plan.exercices.map(e => e.id));
      this.objectif ||= plan.titre;
    }
  }

  enregistrer(): void {
    this.erreur.set(null);
    this.envoi.set(true);
    const s = this.seance();
    const body = {
      patientId: this.patientId, dateHeure: this.dateHeure, duree: this.duree, objectif: this.objectif,
      exerciceIds: this.exerciceIds(), planId: this.planId, nombre: this.nombre, intervalleJours: this.intervalleJours,
    };
    const req = s
      ? this.api.put<{ message: string }>(`/kine/seances/${s.id}`, s.facturee ? { ...body, exerciceIds: undefined } : body)
      : this.api.post<{ message: string }>('/kine/seances', body);
    req.subscribe({
      next: r => { this.envoi.set(false); this.enregistre.emit(r.message); },
      error: e => { this.envoi.set(false); this.erreur.set(errorMessage(e)); },
    });
  }
}
