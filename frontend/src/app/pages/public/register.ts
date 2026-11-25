import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { errorMessage } from '../../core/api';
import { Auth, RegisterData } from '../../services/auth';
import { Alert } from '../../shared/alert';

/** US-04 : le patient crée son compte après son 1er RDV (validation par le gérant ensuite). */
@Component({
  selector: 'app-register',
  imports: [FormsModule, RouterLink, Alert],
  template: `
    <div class="container py-5" style="max-width: 760px">
      <h1 class="page-title">Créer mon compte patient</h1>
      <div class="alert alert-info small">
        <i class="bi bi-info-circle me-1"></i>
        L'inscription est ouverte <strong>après votre premier rendez-vous</strong> : utilisez la même adresse e-mail que pour votre demande.
        Votre compte sera activé dès sa validation par le gérant.
      </div>
      <div class="card p-4">
        <app-alert [error]="erreur()" [success]="succes()" />
        @if (!succes()) {
          <form #f="ngForm" (ngSubmit)="envoyer()">
            <div class="row g-3">
              <div class="col-sm-6"><label class="form-label">Prénom *</label><input class="form-control" name="firstName" [(ngModel)]="form.firstName" required></div>
              <div class="col-sm-6"><label class="form-label">Nom *</label><input class="form-control" name="lastName" [(ngModel)]="form.lastName" required></div>
              <div class="col-sm-6"><label class="form-label">E-mail *</label><input class="form-control" type="email" name="email" [(ngModel)]="form.email" required email></div>
              <div class="col-sm-6"><label class="form-label">Téléphone</label><input class="form-control" type="tel" name="phone" [(ngModel)]="form.phone"></div>
              <div class="col-sm-6"><label class="form-label">Mot de passe * (8 caractères min.)</label><input class="form-control" type="password" name="password" [(ngModel)]="form.password" required minlength="8"></div>
              <div class="col-sm-6"><label class="form-label">Confirmer le mot de passe *</label><input class="form-control" type="password" name="confirm" [(ngModel)]="confirm" required></div>
              <div class="col-sm-6"><label class="form-label">Date de naissance</label><input class="form-control" type="date" name="dateNaissance" [(ngModel)]="form.dateNaissance"></div>
              <div class="col-sm-6"><label class="form-label">Profession</label><input class="form-control" name="qualification" [(ngModel)]="form.qualification"></div>
              <div class="col-12"><label class="form-label">Adresse</label><input class="form-control" name="adresse" [(ngModel)]="form.adresse"></div>
              <div class="col-sm-6"><label class="form-label">Médecin traitant</label><input class="form-control" name="medecinTraitant" [(ngModel)]="form.medecinTraitant"></div>
              <div class="col-sm-6"><label class="form-label">Type de traitement / pathologie</label><input class="form-control" name="typeTraitement" [(ngModel)]="form.typeTraitement"></div>
            </div>
            @if (confirm && confirm !== form.password) { <div class="text-danger small mt-2">Les mots de passe ne correspondent pas.</div> }
            <button class="btn btn-brand btn-lg w-100 mt-4" [disabled]="f.invalid || confirm !== form.password || envoi()">Créer mon compte</button>
          </form>
        } @else {
          <a class="btn btn-brand" routerLink="/connexion">Aller à la connexion</a>
        }
        <p class="text-center mt-3 mb-0 small">Déjà un compte ? <a routerLink="/connexion">Se connecter</a></p>
      </div>
    </div>
  `,
})
export class Register {
  private auth = inject(Auth);

  form: RegisterData = { firstName: '', lastName: '', email: '', password: '', phone: '', dateNaissance: '', adresse: '', qualification: '', medecinTraitant: '', typeTraitement: '' };
  confirm = '';
  envoi = signal(false);
  erreur = signal<string | null>(null);
  succes = signal<string | null>(null);

  envoyer(): void {
    this.envoi.set(true);
    this.erreur.set(null);
    this.auth.register(this.form).subscribe({
      next: r => { this.succes.set(r.message); this.envoi.set(false); },
      error: e => { this.erreur.set(errorMessage(e)); this.envoi.set(false); },
    });
  }
}
