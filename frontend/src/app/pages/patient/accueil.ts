import { Component, inject } from '@angular/core';
import { Auth } from '../../services/auth';

/** Accueil de l'espace patient. */
@Component({
  selector: 'app-patient-accueil',
  template: `
    <h1 class="page-title">Bonjour {{ auth.user()?.firstName }} 👋</h1>
    <div class="card p-4">Bienvenue dans votre espace patient SmartKiné.</div>
  `,
})
export class PatientAccueil {
  auth = inject(Auth);
}
