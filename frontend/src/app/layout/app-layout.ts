import { Component, computed, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { Auth } from '../services/auth';
import { MENUS } from './menus';

/** Mise en page des espaces connectés : menu latéral (repliable sur mobile) + barre du haut. */
@Component({
  selector: 'app-app-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    <div class="app-shell">
      <aside class="sidebar" [class.open]="menuOpen()">
        <div class="d-flex align-items-center justify-content-between px-3 py-3">
          <a class="fs-5 fw-bold text-white text-decoration-none" routerLink="/"><i class="bi bi-heart-pulse-fill me-1"></i>SmartKiné</a>
          <button class="btn btn-sm btn-outline-light d-lg-none" (click)="menuOpen.set(false)" aria-label="Fermer"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="px-3 pb-2 small text-white-50 text-uppercase">{{ roleLabel() }}</div>
        <nav class="nav flex-column px-2" (click)="menuOpen.set(false)">
          @for (item of menu(); track item.link) {
            <a class="nav-link" [routerLink]="item.link" routerLinkActive="active" [routerLinkActiveOptions]="{ exact: true }">
              <i class="bi bi-{{ item.icon }} me-2"></i>{{ item.label }}
            </a>
          }
        </nav>
      </aside>
      @if (menuOpen()) { <div class="sidebar-backdrop d-lg-none" (click)="menuOpen.set(false)"></div> }

      <div class="app-main">
        <header class="topbar d-flex align-items-center gap-2 px-3">
          <button class="btn btn-light d-lg-none" (click)="menuOpen.set(true)" aria-label="Menu"><i class="bi bi-list"></i></button>
          <div class="ms-auto d-flex align-items-center gap-2">
            <span class="d-none d-sm-inline small text-muted">{{ auth.user()?.firstName }} {{ auth.user()?.lastName }}</span>
            <button class="btn btn-outline-secondary btn-sm" (click)="logout()"><i class="bi bi-box-arrow-right"></i><span class="d-none d-sm-inline ms-1">Déconnexion</span></button>
          </div>
        </header>
        <main class="p-3 p-md-4"><router-outlet /></main>
      </div>
    </div>
  `,
})
export class AppLayout {
  auth = inject(Auth);
  private router = inject(Router);

  menuOpen = signal(false);
  menu = computed(() => MENUS[this.auth.user()?.type ?? 'patient']);
  roleLabel = computed(() => ({ gerant: 'Espace gérant', kine: 'Espace kinésithérapeute', patient: 'Espace patient' })[this.auth.user()?.type ?? 'patient']);

  logout(): void {
    this.auth.logout();
    this.router.navigate(['/connexion']);
  }
}
