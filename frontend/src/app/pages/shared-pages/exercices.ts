import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Api, errorMessage } from '../../core/api';
import { BACKEND_URL } from '../../core/config';
import { Exercice } from '../../core/models';
import { Auth } from '../../services/auth';
import { Alert } from '../../shared/alert';

interface ExoForm { id: number | null; nom: string; duree: number; cout: number; objectif: string; }

/**
 * Banque d'exercices partagée :
 *  - kiné (US-19) : ajout / modification (nom, durée, objectif, image)
 *  - gérant (US-30) : idem + tarification (coût) et suppression
 */
@Component({
  selector: 'app-exercices',
  imports: [FormsModule, Alert],
  template: `
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h1 class="page-title mb-0">Banque d'exercices</h1>
      <div class="d-flex gap-2">
        <input class="form-control" placeholder="Rechercher..." [ngModel]="q()" (ngModelChange)="q.set($event)" style="max-width: 220px">
        <button class="btn btn-brand text-nowrap" (click)="nouveau()"><i class="bi bi-plus-lg me-1"></i>Ajouter</button>
      </div>
    </div>
    <app-alert [error]="erreur()" [success]="succes()" />

    @if (form(); as f) {
      <div class="card p-4 mb-3">
        <h5 class="mb-3">{{ f.id ? "Modifier l'exercice" : 'Nouvel exercice' }}</h5>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Nom *</label><input class="form-control" [(ngModel)]="f.nom"></div>
          <div class="col-6 col-md-3"><label class="form-label">Durée (min) *</label><input class="form-control" type="number" min="1" [(ngModel)]="f.duree"></div>
          <div class="col-6 col-md-3">
            <label class="form-label">Coût (DT)</label>
            <input class="form-control" type="number" min="0" step="0.5" [(ngModel)]="f.cout" [disabled]="!estGerant()">
            @if (!estGerant()) { <div class="form-text">Fixé par le gérant</div> }
          </div>
          <div class="col-12"><label class="form-label">Objectif / consignes</label><textarea class="form-control" rows="2" [(ngModel)]="f.objectif"></textarea></div>
          <div class="col-md-6"><label class="form-label">Image (JPG/PNG, 3 Mo max)</label><input class="form-control" type="file" accept="image/*" (change)="fichier = $any($event.target).files[0] ?? null"></div>
        </div>
        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-brand" (click)="enregistrer(f)">Enregistrer</button>
          <button class="btn btn-light" (click)="form.set(null)">Annuler</button>
        </div>
      </div>
    }

    <div class="row g-3">
      @for (e of filtres(); track e.id) {
        <div class="col-sm-6 col-lg-4 col-xl-3">
          <div class="card h-100">
            @if (e.image) { <img [src]="backend + e.image" class="exo-img" [alt]="e.nom"> }
            @else { <div class="exo-img d-flex align-items-center justify-content-center text-brand fs-1"><i class="bi bi-person-arms-up"></i></div> }
            <div class="p-3 d-flex flex-column flex-grow-1">
              <h6 class="fw-bold mb-1">{{ e.nom }}</h6>
              <div class="small text-muted mb-2"><i class="bi bi-stopwatch me-1"></i>{{ e.duree }} min · <strong>{{ e.cout }} DT</strong></div>
              <p class="small mb-3 flex-grow-1">{{ e.objectif }}</p>
              <div class="d-flex gap-2">
                <button class="btn btn-sm btn-light flex-fill" (click)="modifier(e)"><i class="bi bi-pencil"></i> Modifier</button>
                @if (estGerant()) { <button class="btn btn-sm btn-outline-danger" (click)="supprimer(e)" title="Supprimer"><i class="bi bi-trash"></i></button> }
              </div>
            </div>
          </div>
        </div>
      } @empty {
        <div class="col-12"><div class="card empty">Aucun exercice.</div></div>
      }
    </div>
  `,
})
export class ExercicesPage {
  private api = inject(Api);
  private auth = inject(Auth);
  backend = BACKEND_URL;

  exercices = signal<Exercice[]>([]);
  q = signal('');
  form = signal<ExoForm | null>(null);
  fichier: File | null = null;
  erreur = signal<string | null>(null);
  succes = signal<string | null>(null);
  estGerant = computed(() => this.auth.user()?.type === 'gerant');
  filtres = computed(() => {
    const q = this.q().toLowerCase();
    return this.exercices().filter(e => !q || e.nom.toLowerCase().includes(q) || (e.objectif ?? '').toLowerCase().includes(q));
  });

  constructor() {
    this.charger();
  }

  charger(): void {
    this.api.get<Exercice[]>('/exercices').subscribe(l => this.exercices.set(l));
  }

  nouveau(): void {
    this.fichier = null;
    this.form.set({ id: null, nom: '', duree: 10, cout: 0, objectif: '' });
  }

  modifier(e: Exercice): void {
    this.fichier = null;
    this.form.set({ id: e.id, nom: e.nom, duree: e.duree, cout: Number(e.cout), objectif: e.objectif ?? '' });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  enregistrer(f: ExoForm): void {
    this.erreur.set(null);
    const req = f.id
      ? this.api.put<{ message: string; exercice: Exercice }>(`/exercices/${f.id}`, f)
      : this.api.post<{ message: string; exercice: Exercice }>('/exercices', f);
    req.subscribe({
      next: r => {
        if (this.fichier) {
          const data = new FormData();
          data.append('image', this.fichier);
          this.api.upload(`/exercices/${r.exercice.id}/image`, data).subscribe({
            next: () => this.charger(),
            error: e => this.erreur.set(errorMessage(e)),
          });
        }
        this.succes.set(r.message);
        this.form.set(null);
        this.charger();
      },
      error: e => this.erreur.set(errorMessage(e)),
    });
  }

  supprimer(e: Exercice): void {
    if (!confirm(`Supprimer « ${e.nom} » de la banque ?`)) {
      return;
    }
    this.api.delete<{ message: string }>(`/exercices/${e.id}`).subscribe({
      next: r => { this.succes.set(r.message); this.charger(); },
      error: err => this.erreur.set(errorMessage(err)),
    });
  }
}
