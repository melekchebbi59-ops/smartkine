import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Api, errorMessage } from '../../core/api';
import { User } from '../../core/models';
import { Alert } from '../../shared/alert';

/** Profil de l'utilisateur connecté (tous les rôles). */
@Component({
  selector: 'app-profil',
  imports: [FormsModule, Alert],
  template: `
    <h1 class="page-title">Mon profil</h1>
    <app-alert [error]="erreur()" [success]="succes()" />
    @if (user(); as u) {
      <div class="row g-3">
        <div class="col-lg-7">
          <div class="card p-4">
            <h5 class="mb-3">Informations</h5>
            <div class="row g-3">
              <div class="col-sm-6"><label class="form-label">Prénom</label><input class="form-control" [(ngModel)]="form.firstName"></div>
              <div class="col-sm-6"><label class="form-label">Nom</label><input class="form-control" [(ngModel)]="form.lastName"></div>
              <div class="col-sm-6"><label class="form-label">E-mail</label><input class="form-control" [value]="u.email" disabled></div>
              <div class="col-sm-6"><label class="form-label">Téléphone</label><input class="form-control" [(ngModel)]="form.phone"></div>
              @if (u.type === 'patient') {
                <div class="col-sm-6"><label class="form-label">Date de naissance</label><input class="form-control" type="date" [(ngModel)]="form.dateNaissance"></div>
                <div class="col-sm-6"><label class="form-label">Adresse</label><input class="form-control" [(ngModel)]="form.adresse"></div>
                <div class="col-12 small text-muted">
                  Kiné traitant : <strong>{{ u.kine?.fullName ?? 'pas encore affecté' }}</strong>
                </div>
              }
              @if (u.type === 'kine') {
                <div class="col-12 small text-muted">Spécialité : <strong>{{ u.specialite ?? '—' }}</strong></div>
              }
            </div>
          </div>
        </div>
        <div class="col-lg-5">
          <div class="card p-4">
            <h5 class="mb-3">Changer de mot de passe</h5>
            <label class="form-label">Mot de passe actuel</label>
            <input class="form-control mb-2" type="password" [(ngModel)]="form.currentPassword">
            <label class="form-label">Nouveau mot de passe</label>
            <input class="form-control" type="password" [(ngModel)]="form.newPassword" minlength="8">
          </div>
        </div>
      </div>
      <button class="btn btn-brand mt-3" (click)="enregistrer()">Enregistrer</button>
    }
  `,
})
export class Profil {
  private api = inject(Api);

  user = signal<User | null>(null);
  form = { firstName: '', lastName: '', phone: '', dateNaissance: '', adresse: '', currentPassword: '', newPassword: '' };
  erreur = signal<string | null>(null);
  succes = signal<string | null>(null);

  constructor() {
    this.api.get<User>('/me').subscribe(u => this.remplir(u));
  }

  private remplir(u: User): void {
    this.user.set(u);
    this.form = { firstName: u.firstName, lastName: u.lastName, phone: u.phone ?? '', dateNaissance: u.dateNaissance ?? '', adresse: u.adresse ?? '', currentPassword: '', newPassword: '' };
  }

  enregistrer(): void {
    this.erreur.set(null);
    this.succes.set(null);
    this.api.put<{ message: string; user: User }>('/me', this.form).subscribe({
      next: r => { this.succes.set(r.message); this.remplir(r.user); },
      error: e => this.erreur.set(errorMessage(e)),
    });
  }
}
