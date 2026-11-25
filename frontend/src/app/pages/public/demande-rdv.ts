import { Component, inject, signal } from '@angular/core';
import { FormsModule, NgForm } from '@angular/forms';
import { Api, errorMessage } from '../../core/api';
import { Alert } from '../../shared/alert';

/** US-02 / US-03 : formulaire de demande de premier rendez-vous. */
@Component({
  selector: 'app-demande-rdv',
  imports: [FormsModule, Alert],
  template: `
    <div class="container py-5" style="max-width: 720px">
      <h1 class="page-title">Demander un premier rendez-vous</h1>
      <p class="text-muted">Remplissez ce formulaire : vous recevrez un e-mail de confirmation, puis le gérant du centre vous recontactera pour fixer le rendez-vous.</p>

      <div class="card p-4">
        <app-alert [error]="erreur()" [success]="succes()" />
        @if (!succes()) {
          <form #f="ngForm" (ngSubmit)="envoyer(f)">
            <div class="row g-3">
              <div class="col-sm-6">
                <label class="form-label" for="prenom">Prénom *</label>
                <input class="form-control" id="prenom" name="prenom" [(ngModel)]="form.prenom" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="nom">Nom *</label>
                <input class="form-control" id="nom" name="nom" [(ngModel)]="form.nom" required>
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="tel">Téléphone *</label>
                <input class="form-control" id="tel" name="telephone" type="tel" [(ngModel)]="form.telephone" required pattern="[0-9 +]{8,20}" placeholder="22 333 444">
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="email">E-mail *</label>
                <input class="form-control" id="email" name="email" type="email" [(ngModel)]="form.email" required email>
              </div>
              <div class="col-sm-6">
                <label class="form-label" for="date">Date et heure souhaitées *</label>
                <input class="form-control" id="date" name="dateSouhaitee" type="datetime-local" [min]="min" [(ngModel)]="form.dateSouhaitee" required>
              </div>
              <div class="col-12">
                <label class="form-label" for="motif">Motif / pathologie</label>
                <textarea class="form-control" id="motif" name="motif" rows="3" [(ngModel)]="form.motif" placeholder="Ex. : entorse de la cheville, douleurs lombaires..."></textarea>
              </div>
            </div>
            <button class="btn btn-brand btn-lg w-100 mt-4" [disabled]="f.invalid || envoi()">
              @if (envoi()) { <span class="spinner-border spinner-border-sm me-2"></span> }
              Envoyer ma demande
            </button>
          </form>
        } @else {
          <button class="btn btn-outline-primary" (click)="succes.set(null)">Faire une autre demande</button>
        }
      </div>
    </div>
  `,
})
export class DemandeRdvPage {
  private api = inject(Api);

  form = { nom: '', prenom: '', telephone: '', email: '', dateSouhaitee: '', motif: '' };
  min = new Date().toISOString().slice(0, 16);
  envoi = signal(false);
  erreur = signal<string | null>(null);
  succes = signal<string | null>(null);

  envoyer(f: NgForm): void {
    this.envoi.set(true);
    this.erreur.set(null);
    this.api.post<{ message: string }>('/public/demandes-rdv', this.form).subscribe({
      next: r => {
        this.succes.set(r.message);
        this.envoi.set(false);
        f.resetForm();
      },
      error: e => {
        this.erreur.set(errorMessage(e));
        this.envoi.set(false);
      },
    });
  }
}
