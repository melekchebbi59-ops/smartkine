import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Api } from '../../core/api';
import { User } from '../../core/models';

/** US-14 : liste des patients affectés au kiné. */
@Component({
  selector: 'app-kine-patients',
  imports: [RouterLink, FormsModule],
  template: `
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h1 class="page-title mb-0">Mes patients</h1>
      <input class="form-control" style="max-width: 260px" placeholder="Rechercher..." [ngModel]="q()" (ngModelChange)="q.set($event)">
    </div>
    <div class="row g-3">
      @for (p of filtres(); track p.id) {
        <div class="col-sm-6 col-xl-4">
          <a [routerLink]="['/kine/patients', p.id]" class="card p-3 h-100 text-decoration-none text-body">
            <div class="d-flex align-items-center gap-3">
              <span class="feature-icon rounded-circle">{{ p.firstName[0] }}{{ p.lastName[0] }}</span>
              <div>
                <strong>{{ p.fullName }}</strong>
                <div class="small text-muted">{{ p.typeTraitement ?? 'Pathologie non renseignée' }}</div>
                <div class="small text-muted"><i class="bi bi-telephone"></i> {{ p.phone ?? '—' }}</div>
              </div>
            </div>
          </a>
        </div>
      } @empty {
        <div class="col-12"><div class="card empty">Aucun patient ne vous est encore affecté.</div></div>
      }
    </div>
  `,
})
export class KinePatients {
  private api = inject(Api);
  patients = signal<User[]>([]);
  q = signal('');
  filtres = computed(() => {
    const q = this.q().toLowerCase();
    return this.patients().filter(p => !q || p.fullName.toLowerCase().includes(q) || (p.typeTraitement ?? '').toLowerCase().includes(q));
  });

  constructor() {
    this.api.get<User[]>('/kine/patients').subscribe(l => this.patients.set(l));
  }
}
