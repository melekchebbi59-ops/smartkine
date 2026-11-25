import { Component, OnDestroy, computed, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { NotificationsService } from '../core/notifications';
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
            <a routerLink="/messages" class="btn btn-light position-relative" title="Messages" aria-label="Messages">
              <i class="bi bi-chat-dots"></i>
              @if (notifs.messagesNonLus()) { <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{ notifs.messagesNonLus() }}</span> }
            </a>
            <div class="position-relative">
              <button class="btn btn-light position-relative" (click)="notifOpen.set(!notifOpen())" title="Notifications" aria-label="Notifications">
                <i class="bi bi-bell"></i>
                @if (notifs.nonLues()) { <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{ notifs.nonLues() }}</span> }
              </button>
              @if (notifOpen()) {
                <div class="card position-absolute end-0 mt-2 shadow" style="width: min(340px, 90vw); z-index: 1050">
                  <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span>Notifications</span>
                    @if (notifs.nonLues()) { <button class="btn btn-link btn-sm p-0" (click)="notifs.toutLire()">Tout marquer lu</button> }
                  </div>
                  <div class="list-group list-group-flush" style="max-height: 360px; overflow-y: auto">
                    @for (n of notifs.notifications(); track n.id) {
                      <div class="list-group-item small" [class.fw-semibold]="!n.lue" [class.bg-brand-light]="!n.lue">
                        {{ n.texte }}<div class="text-muted fw-normal" style="font-size: .75rem">{{ quand(n.createdAt) }}</div>
                      </div>
                    } @empty { <div class="list-group-item small text-muted">Aucune notification.</div> }
                  </div>
                </div>
              }
            </div>
            <span class="d-none d-sm-inline small text-muted">{{ auth.user()?.firstName }} {{ auth.user()?.lastName }}</span>
            <button class="btn btn-outline-secondary btn-sm" (click)="logout()"><i class="bi bi-box-arrow-right"></i><span class="d-none d-sm-inline ms-1">Déconnexion</span></button>
          </div>
        </header>
        <main class="p-3 p-md-4"><router-outlet /></main>
      </div>
    </div>
  `,
})
export class AppLayout implements OnDestroy {
  auth = inject(Auth);
  notifs = inject(NotificationsService);
  private router = inject(Router);

  menuOpen = signal(false);
  notifOpen = signal(false);
  menu = computed(() => MENUS[this.auth.user()?.type ?? 'patient']);
  roleLabel = computed(() => ({ gerant: 'Espace gérant', kine: 'Espace kinésithérapeute', patient: 'Espace patient' })[this.auth.user()?.type ?? 'patient']);

  constructor() {
    this.notifs.demarrer();
  }

  ngOnDestroy(): void {
    this.notifs.arreter();
  }

  quand(iso: string): string {
    return new Date(iso).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
  }

  logout(): void {
    this.notifs.arreter();
    this.auth.logout();
    this.router.navigate(['/connexion']);
  }
}
