import { Component, computed, inject, signal } from '@angular/core';
import { DomSanitizer } from '@angular/platform-browser';
import { RouterLink } from '@angular/router';
import { Api } from '../../core/api';
import { Centre } from '../../core/models';

/** US-01 : vitrine publique du centre (horaires, services, contacts, localisation). */
@Component({
  selector: 'app-home',
  imports: [RouterLink],
  template: `
    <section class="hero">
      <div class="container">
        <div class="row align-items-center g-4">
          <div class="col-lg-7">
            <h1 class="display-5 fw-bold">Votre rééducation, simplement.</h1>
            <p class="lead opacity-75 my-3">
              {{ centre()?.nom ?? 'SmartKiné' }} vous accompagne de la première consultation jusqu'à la fin de votre traitement :
              planning des séances, plan de rééducation et suivi des paiements, depuis votre ordinateur ou votre smartphone.
            </p>
            <div class="d-flex flex-wrap gap-2">
              <a routerLink="/rendez-vous" class="btn btn-light btn-lg text-brand fw-semibold"><i class="bi bi-calendar-plus me-1"></i>Demander un 1er rendez-vous</a>
              <a routerLink="/connexion" class="btn btn-outline-light btn-lg">Espace patient</a>
            </div>
          </div>
          <div class="col-lg-5 d-none d-lg-block text-center">
            <i class="bi bi-heart-pulse" style="font-size: 10rem; opacity: .35"></i>
          </div>
        </div>
      </div>
    </section>

    <section class="container py-5">
      <h2 class="fw-bold mb-4 text-center">Nos services</h2>
      <div class="row g-3">
        @for (s of centre()?.services ?? []; track s.titre; let i = $index) {
          <div class="col-sm-6 col-lg-4">
            <div class="card h-100 p-3">
              <span class="feature-icon mb-2"><i class="bi bi-{{ icons[i % icons.length] }}"></i></span>
              <h5 class="fw-semibold">{{ s.titre }}</h5>
              <p class="text-muted mb-0">{{ s.description }}</p>
            </div>
          </div>
        }
      </div>
    </section>

    <section class="container pb-4">
      <div class="row g-3">
        <div class="col-lg-5">
          <div class="card h-100 p-4">
            <h4 class="fw-bold mb-3"><i class="bi bi-clock text-brand me-2"></i>Horaires</h4>
            <table class="table table-sm mb-4">
              <tbody>
                @for (h of centre()?.horaires ?? []; track h.jours) {
                  <tr><td>{{ h.jours }}</td><td class="text-end fw-semibold">{{ h.heures }}</td></tr>
                }
              </tbody>
            </table>
            <h4 class="fw-bold mb-3"><i class="bi bi-telephone text-brand me-2"></i>Contact</h4>
            <p class="mb-1"><i class="bi bi-geo-alt me-2"></i>{{ centre()?.adresse }}</p>
            <p class="mb-1"><i class="bi bi-telephone me-2"></i><a [href]="'tel:' + centre()?.telephone">{{ centre()?.telephone }}</a></p>
            <p class="mb-0"><i class="bi bi-envelope me-2"></i><a [href]="'mailto:' + centre()?.email">{{ centre()?.email }}</a></p>
          </div>
        </div>
        <div class="col-lg-7">
          <div class="card h-100 overflow-hidden">
            @if (mapUrl()) {
              <iframe [src]="mapUrl()" title="Localisation du centre" style="border:0; width:100%; min-height:340px; height:100%" loading="lazy"></iframe>
            }
          </div>
        </div>
      </div>
      @if (erreur()) { <div class="alert alert-warning mt-3">{{ erreur() }}</div> }
    </section>
  `,
})
export class Home {
  private api = inject(Api);
  private sanitizer = inject(DomSanitizer);

  icons = ['bandaid', 'bicycle', 'activity', 'lungs', 'hand-index-thumb'];
  centre = signal<Centre | null>(null);
  erreur = signal<string | null>(null);

  mapUrl = computed(() => {
    const c = this.centre();
    if (!c) {
      return null;
    }
    const d = 0.01;
    const url = `https://www.openstreetmap.org/export/embed.html?bbox=${c.longitude - d},${c.latitude - d},${c.longitude + d},${c.latitude + d}&layer=mapnik&marker=${c.latitude},${c.longitude}`;
    return this.sanitizer.bypassSecurityTrustResourceUrl(url);
  });

  constructor() {
    this.api.get<Centre>('/public/centre').subscribe({
      next: c => this.centre.set(c),
      error: () => this.erreur.set("Les informations du centre ne sont pas disponibles (backend arrêté ?)."),
    });
  }
}
