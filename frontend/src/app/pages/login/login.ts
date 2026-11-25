import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { errorMessage } from '../../core/api';
import { Auth } from '../../services/auth';

/**
 * US-05 / US-13 / US-21 : connexion sécurisée (patient, kiné, gérant).
 * Correction Sprint 1 : le formulaire appelle maintenant l'API et redirige selon le rôle.
 */
@Component({
  selector: 'app-login',
  imports: [FormsModule, RouterLink],
  templateUrl: './login.html',
  styleUrl: './login.css'
})
export class Login {
  private auth = inject(Auth);
  private router = inject(Router);

  email = '';
  password = '';
  loading = signal(false);
  error = signal<string | null>(null);
  expired = inject(ActivatedRoute).snapshot.queryParamMap.has('expire');

  onSubmit(): void {
    this.loading.set(true);
    this.error.set(null);

    this.auth.login({ email: this.email, password: this.password }).subscribe({
      next: () => {
        this.loading.set(false);
        this.router.navigateByUrl(this.auth.homeUrl());
      },
      error: (err) => {
        this.loading.set(false);
        this.error.set(err.status === 401 ? (err.error?.message === 'Invalid credentials.' ? 'E-mail ou mot de passe incorrect.' : err.error?.message) : errorMessage(err));
      },
    });
  }
}
