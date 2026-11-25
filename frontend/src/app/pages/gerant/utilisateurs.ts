import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Api, errorMessage } from '../../core/api';
import { User, UserType } from '../../core/models';
import { Alert } from '../../shared/alert';
import { Badge } from '../../shared/badge';

interface UserForm {
  id: number | null;
  type: 'kine' | 'gerant';
  firstName: string;
  lastName: string;
  email: string;
  phone: string;
  specialite: string;
  password: string;
}

/** US-22 : création des comptes kinés + gestion (consulter, modifier, désactiver) de tous les utilisateurs. */
@Component({
  selector: 'app-gerant-utilisateurs',
  imports: [FormsModule, Alert, Badge],
  template: `
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <h1 class="page-title mb-0">Utilisateurs</h1>
      <button class="btn btn-brand" (click)="nouveau()"><i class="bi bi-person-plus me-1"></i>Nouveau kiné</button>
    </div>
    <app-alert [error]="erreur()" [success]="succes()" />

    @if (form(); as f) {
      <div class="card p-4 mb-3">
        <h5 class="mb-3">{{ f.id ? 'Modifier le compte' : 'Créer un compte' }}</h5>
        <div class="row g-3">
          @if (!f.id) {
            <div class="col-sm-4">
              <label class="form-label">Type de compte</label>
              <select class="form-select" [(ngModel)]="f.type"><option value="kine">Kinésithérapeute</option><option value="gerant">Gérant</option></select>
            </div>
          }
          <div class="col-sm-4"><label class="form-label">Prénom *</label><input class="form-control" [(ngModel)]="f.firstName"></div>
          <div class="col-sm-4"><label class="form-label">Nom *</label><input class="form-control" [(ngModel)]="f.lastName"></div>
          <div class="col-sm-4"><label class="form-label">E-mail *</label><input class="form-control" type="email" [(ngModel)]="f.email"></div>
          <div class="col-sm-4"><label class="form-label">Téléphone</label><input class="form-control" [(ngModel)]="f.phone"></div>
          @if (f.type === 'kine') {
            <div class="col-sm-4"><label class="form-label">Spécialité</label><input class="form-control" [(ngModel)]="f.specialite" placeholder="Sport, neurologie..."></div>
          }
          <div class="col-sm-4"><label class="form-label">{{ f.id ? 'Nouveau mot de passe (optionnel)' : 'Mot de passe * (8 car. min.)' }}</label><input class="form-control" type="password" [(ngModel)]="f.password"></div>
        </div>
        <div class="mt-3 d-flex gap-2">
          <button class="btn btn-brand" (click)="enregistrer(f)">Enregistrer</button>
          <button class="btn btn-light" (click)="form.set(null)">Annuler</button>
        </div>
      </div>
    }

    <ul class="nav nav-pills mb-3 flex-nowrap overflow-auto">
      @for (t of onglets; track t.value) {
        <li class="nav-item"><button class="nav-link text-nowrap" [class.active]="type() === t.value" (click)="type.set(t.value); charger()">{{ t.label }}</button></li>
      }
    </ul>

    <div class="card">
      <div class="table-responsive">
        <table class="table mb-0">
          <thead><tr><th>Nom</th><th class="d-none d-md-table-cell">E-mail / téléphone</th><th>Rôle</th><th class="d-none d-lg-table-cell">Détails</th><th>État</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
            @for (u of users(); track u.id) {
              <tr [class.opacity-50]="!u.active">
                <td><strong>{{ u.fullName }}</strong></td>
                <td class="d-none d-md-table-cell small">{{ u.email }}<br>{{ u.phone }}</td>
                <td><span class="badge text-bg-light border">{{ roles[u.type] }}</span></td>
                <td class="d-none d-lg-table-cell small">
                  @if (u.type === 'kine') { {{ u.specialite ?? '—' }} · {{ u.nbPatients }} patient(s) }
                  @if (u.type === 'patient') { <app-badge [statut]="u.statutInscription ?? ''" kind="demande" /> {{ u.kine ? '· ' + u.kine.fullName : '' }} }
                </td>
                <td>@if (u.active) { <span class="badge text-bg-success">Actif</span> } @else { <span class="badge text-bg-secondary">Désactivé</span> }</td>
                <td class="text-end text-nowrap">
                  @if (u.type !== 'patient') {
                    <button class="btn btn-sm btn-light me-1" (click)="modifier(u)" title="Modifier"><i class="bi bi-pencil"></i></button>
                  }
                  <button class="btn btn-sm" [class.btn-outline-danger]="u.active" [class.btn-outline-success]="!u.active" (click)="basculer(u)" [title]="u.active ? 'Désactiver' : 'Activer'">
                    <i class="bi" [class.bi-person-slash]="u.active" [class.bi-person-check]="!u.active"></i>
                  </button>
                </td>
              </tr>
            } @empty {
              <tr><td colspan="6" class="empty">Aucun utilisateur.</td></tr>
            }
          </tbody>
        </table>
      </div>
    </div>
  `,
})
export class GerantUtilisateurs {
  private api = inject(Api);

  onglets: { value: UserType | ''; label: string }[] = [
    { value: '', label: 'Tous' }, { value: 'kine', label: 'Kinés' }, { value: 'patient', label: 'Patients' }, { value: 'gerant', label: 'Gérants' },
  ];
  roles: Record<UserType, string> = { gerant: 'Gérant', kine: 'Kiné', patient: 'Patient' };

  type = signal<UserType | ''>('');
  users = signal<User[]>([]);
  form = signal<UserForm | null>(null);
  erreur = signal<string | null>(null);
  succes = signal<string | null>(null);
  kines = computed(() => this.users().filter(u => u.type === 'kine'));

  constructor() {
    this.charger();
  }

  charger(): void {
    this.api.get<User[]>('/gerant/utilisateurs', { type: this.type() }).subscribe(l => this.users.set(l));
  }

  nouveau(): void {
    this.form.set({ id: null, type: 'kine', firstName: '', lastName: '', email: '', phone: '', specialite: '', password: '' });
  }

  modifier(u: User): void {
    this.form.set({ id: u.id, type: u.type === 'gerant' ? 'gerant' : 'kine', firstName: u.firstName, lastName: u.lastName, email: u.email, phone: u.phone ?? '', specialite: u.specialite ?? '', password: '' });
  }

  enregistrer(f: UserForm): void {
    this.erreur.set(null);
    const req = f.id
      ? this.api.put<{ message: string }>(`/gerant/utilisateurs/${f.id}`, f)
      : this.api.post<{ message: string }>('/gerant/utilisateurs', f);
    req.subscribe({
      next: r => { this.succes.set(r.message); this.form.set(null); this.charger(); },
      error: e => this.erreur.set(errorMessage(e)),
    });
  }

  basculer(u: User): void {
    if (u.active && !confirm(`Désactiver le compte de ${u.fullName} ? Il ne pourra plus se connecter.`)) {
      return;
    }
    this.api.patch<{ message: string }>(`/gerant/utilisateurs/${u.id}/statut`, { active: !u.active }).subscribe({
      next: r => { this.succes.set(r.message); this.charger(); },
      error: e => this.erreur.set(errorMessage(e)),
    });
  }
}
