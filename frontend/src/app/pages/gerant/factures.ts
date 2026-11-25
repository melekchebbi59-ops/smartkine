import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Api, errorMessage } from '../../core/api';
import { Facture, Seance, User } from '../../core/models';
import { Alert } from '../../shared/alert';
import { Badge } from '../../shared/badge';
import { dateFr, dt } from '../../shared/format';

/** US-25 : établir les factures. US-26 : saisir les paiements partiels (reste à payer automatique). */
@Component({
  selector: 'app-gerant-factures',
  imports: [FormsModule, Alert, Badge],
  template: `
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h1 class="page-title mb-0">Factures & paiements</h1>
      <button class="btn btn-brand" (click)="ouvrirCreation()"><i class="bi bi-receipt me-1"></i>Établir une facture</button>
    </div>
    <app-alert [error]="erreur()" [success]="succes()" />

    @if (creation()) {
      <div class="card p-4 mb-3">
        <h5 class="mb-3">Nouvelle facture</h5>
        <label class="form-label">Patient</label>
        <select class="form-select mb-3" [(ngModel)]="patientId" (ngModelChange)="chargerSeances()">
          <option [ngValue]="0" disabled>Choisir un patient...</option>
          @for (p of patients(); track p.id) { <option [ngValue]="p.id">{{ p.fullName }}</option> }
        </select>
        @if (patientId) {
          @if (seances().length) {
            <div class="table-responsive">
              <table class="table table-sm">
                <thead><tr><th></th><th>Séance</th><th>Statut</th><th class="text-end">Prix</th></tr></thead>
                <tbody>
                  @for (s of seances(); track s.id) {
                    <tr>
                      <td><input type="checkbox" class="form-check-input" [checked]="choisies().includes(s.id)" (change)="toggle(s.id)"></td>
                      <td>{{ dateFr(s.dateHeure) }}<div class="small text-muted">{{ s.exercices.length }} exercice(s)</div></td>
                      <td><app-badge [statut]="s.statut" /></td>
                      <td class="text-end">{{ dt(s.prix) }}</td>
                    </tr>
                  }
                </tbody>
                <tfoot><tr><th colspan="3" class="text-end">Total</th><th class="text-end">{{ dt(totalChoisi()) }}</th></tr></tfoot>
              </table>
            </div>
            <div class="small text-muted mb-3">Prix d'une séance = somme des coûts de ses exercices (ou tarif forfaitaire de 40 DT si aucun exercice tarifé).</div>
            <div class="d-flex gap-2">
              <button class="btn btn-brand" [disabled]="!choisies().length" (click)="creer()">Établir la facture</button>
              <button class="btn btn-light" (click)="creation.set(false)">Annuler</button>
            </div>
          } @else {
            <div class="alert alert-light mb-0">Toutes les séances de ce patient sont déjà facturées.</div>
          }
        }
      </div>
    }

    <ul class="nav nav-pills mb-3 flex-nowrap overflow-auto">
      @for (f of filtres; track f.value) {
        <li class="nav-item"><button class="nav-link text-nowrap" [class.active]="filtre() === f.value" (click)="filtre.set(f.value); charger()">{{ f.label }}</button></li>
      }
    </ul>

    <div class="row g-3">
      @for (f of factures(); track f.id) {
        <div class="col-lg-6">
          <div class="card p-3 h-100">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <h6 class="fw-bold mb-0">{{ f.numero }}</h6>
                <div class="small text-muted">{{ f.patient.fullName }} · {{ dateFr(f.dateEmission, false) }} · {{ f.seances.length }} séance(s)</div>
              </div>
              <app-badge [statut]="f.statut" kind="facture" />
            </div>
            <div class="row text-center my-3 g-0">
              <div class="col"><div class="small text-muted">Total</div><strong>{{ dt(f.montantTotal) }}</strong></div>
              <div class="col"><div class="small text-muted">Payé</div><strong class="text-success">{{ dt(f.montantPaye) }}</strong></div>
              <div class="col"><div class="small text-muted">Reste</div><strong [class.text-danger]="+f.resteAPayer > 0">{{ dt(f.resteAPayer) }}</strong></div>
            </div>
            <div class="progress mb-2" style="height: 6px"><div class="progress-bar bg-success" [style.width.%]="(+f.montantPaye / +f.montantTotal) * 100"></div></div>
            @if (f.paiements.length) {
              <ul class="list-unstyled small mb-2">
                @for (p of f.paiements; track p.id) { <li><i class="bi bi-check2 text-success"></i> {{ dateFr(p.datePaiement, false) }} — {{ dt(p.montant) }} ({{ modes[p.mode] ?? p.mode }})</li> }
              </ul>
            }
            @if (+f.resteAPayer > 0) {
              <div class="input-group input-group-sm mt-auto">
                <input type="number" class="form-control" min="0.5" step="0.5" [max]="f.resteAPayer" placeholder="Montant" [(ngModel)]="montants[f.id]">
                <select class="form-select" style="max-width: 120px" [(ngModel)]="modesChoisis[f.id]">
                  @for (m of modeKeys; track m) { <option [value]="m">{{ modes[m] }}</option> }
                </select>
                <button class="btn btn-success" (click)="payer(f)"><i class="bi bi-cash"></i> Encaisser</button>
              </div>
            }
            @if (!f.paiements.length) {
              <button class="btn btn-link btn-sm text-danger p-0 mt-2 align-self-start" (click)="supprimer(f)">Supprimer la facture</button>
            }
          </div>
        </div>
      } @empty {
        <div class="col-12"><div class="card empty">Aucune facture.</div></div>
      }
    </div>
  `,
})
export class GerantFactures {
  private api = inject(Api);
  dateFr = dateFr;
  dt = dt;

  filtres = [{ value: '', label: 'Toutes' }, { value: 'impayee', label: 'Impayées' }, { value: 'partielle', label: 'Partielles' }, { value: 'payee', label: 'Payées' }];
  modes: Record<string, string> = { especes: 'Espèces', carte: 'Carte', cheque: 'Chèque', virement: 'Virement' };
  modeKeys = Object.keys(this.modes);

  factures = signal<Facture[]>([]);
  patients = signal<User[]>([]);
  seances = signal<Seance[]>([]);
  choisies = signal<number[]>([]);
  filtre = signal('');
  creation = signal(false);
  patientId = 0;
  montants: Record<number, number | null> = {};
  modesChoisis: Record<number, string> = {};
  erreur = signal<string | null>(null);
  succes = signal<string | null>(null);

  totalChoisi = computed(() => this.seances().filter(s => this.choisies().includes(s.id)).reduce((t, s) => t + Number(s.prix), 0));

  constructor() {
    this.charger();
  }

  charger(): void {
    this.api.get<Facture[]>('/gerant/factures', { statut: this.filtre() }).subscribe(l => {
      l.forEach(f => (this.modesChoisis[f.id] ??= 'especes'));
      this.factures.set(l);
    });
  }

  ouvrirCreation(): void {
    this.creation.set(true);
    this.patientId = 0;
    this.seances.set([]);
    this.api.get<User[]>('/gerant/patients').subscribe(l => this.patients.set(l));
  }

  chargerSeances(): void {
    this.api.get<Seance[]>(`/gerant/factures/a-facturer/${this.patientId}`).subscribe(l => {
      this.seances.set(l);
      this.choisies.set(l.map(s => s.id));
    });
  }

  toggle(id: number): void {
    const c = this.choisies();
    this.choisies.set(c.includes(id) ? c.filter(x => x !== id) : [...c, id]);
  }

  creer(): void {
    this.api.post<{ message: string }>('/gerant/factures', { patientId: this.patientId, seanceIds: this.choisies() }).subscribe({
      next: r => { this.succes.set(r.message); this.erreur.set(null); this.creation.set(false); this.charger(); },
      error: e => this.erreur.set(errorMessage(e)),
    });
  }

  payer(f: Facture): void {
    this.api.post<{ message: string }>(`/gerant/factures/${f.id}/paiements`, { montant: this.montants[f.id], mode: this.modesChoisis[f.id] }).subscribe({
      next: r => { this.succes.set(r.message); this.erreur.set(null); this.montants[f.id] = null; this.charger(); },
      error: e => this.erreur.set(errorMessage(e)),
    });
  }

  supprimer(f: Facture): void {
    if (confirm(`Supprimer la facture ${f.numero} ?`)) {
      this.api.delete<{ message: string }>(`/gerant/factures/${f.id}`).subscribe({
        next: r => { this.succes.set(r.message); this.charger(); },
        error: e => this.erreur.set(errorMessage(e)),
      });
    }
  }
}
