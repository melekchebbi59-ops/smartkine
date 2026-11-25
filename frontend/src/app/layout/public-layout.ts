import { Component, inject, signal } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { Auth } from '../services/auth';

/** Mise en page de l'espace Visiteur (vitrine). */
@Component({
  selector: 'app-public-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    <nav class="navbar navbar-expand-md bg-white border-bottom sticky-top">
      <div class="container">
        <a class="navbar-brand fw-bold text-brand" routerLink="/"><i class="bi bi-heart-pulse-fill me-1"></i>SmartKiné</a>
        <button class="navbar-toggler" type="button" (click)="open.set(!open())" aria-label="Menu">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" [class.show]="open()">
          <ul class="navbar-nav ms-auto align-items-md-center gap-md-2" (click)="open.set(false)">
            <li class="nav-item"><a class="nav-link" routerLink="/" routerLinkActive="active" [routerLinkActiveOptions]="{ exact: true }">Accueil</a></li>
            <li class="nav-item"><a class="nav-link" routerLink="/rendez-vous" routerLinkActive="active">Prendre rendez-vous</a></li>
            @if (auth.isAuthenticated()) {
              <li class="nav-item"><a class="btn btn-brand" [routerLink]="auth.homeUrl()">Mon espace</a></li>
            } @else {
              <li class="nav-item"><a class="nav-link" routerLink="/inscription" routerLinkActive="active">Créer mon compte</a></li>
              <li class="nav-item"><a class="btn btn-brand" routerLink="/connexion">Connexion</a></li>
            }
          </ul>
        </div>
      </div>
    </nav>
    <main><router-outlet /></main>
    <footer class="bg-dark text-white-50 py-4 mt-5">
      <div class="container small d-flex flex-column flex-md-row justify-content-between gap-2">
        <span>© 2026 SmartKiné — Projet d'intégration MDW3, ISET Sousse</span>
        <span>Équipe 2 : K. Brigui, I. Dardouri, M. Chabbi, F. Ezzar</span>
      </div>
    </footer>
  `,
})
export class PublicLayout {
  auth = inject(Auth);
  open = signal(false);
}
