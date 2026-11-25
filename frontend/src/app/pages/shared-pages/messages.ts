import { Component, ElementRef, computed, inject, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Api, errorMessage } from '../../core/api';
import { Conversation, Message, UserLight } from '../../core/models';
import { NotificationsService } from '../../core/notifications';
import { Auth } from '../../services/auth';
import { Alert } from '../../shared/alert';

/** Messagerie interne : US-11 (patient), US-20 (kiné), US-29 (gérant + diffusion). */
@Component({
  selector: 'app-messages',
  imports: [FormsModule, Alert],
  template: `
    <h1 class="page-title">Messagerie</h1>
    <app-alert [error]="erreur()" [success]="succes()" />

    @if (estGerant()) {
      <details class="card p-3 mb-3">
        <summary class="fw-semibold"><i class="bi bi-megaphone me-1"></i>Diffuser une information</summary>
        <div class="row g-2 mt-2">
          <div class="col-md-3">
            <select class="form-select" [(ngModel)]="cible">
              <option value="tous">Tout le monde</option><option value="kines">Tous les kinés</option><option value="patients">Tous les patients</option>
            </select>
          </div>
          <div class="col-md-7"><input class="form-control" [(ngModel)]="diffusionTexte" placeholder="Ex. : le centre sera fermé le 1er décembre"></div>
          <div class="col-md-2"><button class="btn btn-brand w-100" (click)="diffuser()" [disabled]="!diffusionTexte.trim()">Envoyer</button></div>
        </div>
      </details>
    }

    <div class="card overflow-hidden">
      <div class="row g-0" style="min-height: 65vh">
        <!-- Liste des conversations -->
        <div class="col-md-4 border-end" [class.d-none]="contact()" [class.d-md-block]="true">
          <div class="p-2 border-bottom">
            <select class="form-select form-select-sm" [ngModel]="null" (ngModelChange)="ouvrir($event)">
              <option [ngValue]="null">+ Nouvelle conversation...</option>
              @for (c of contacts(); track c.id) { <option [ngValue]="c">{{ c.fullName }} ({{ roles[c.type] }})</option> }
            </select>
          </div>
          <div class="list-group list-group-flush">
            @for (c of conversations(); track c.contact.id) {
              <button class="list-group-item list-group-item-action" [class.active]="contact()?.id === c.contact.id" (click)="ouvrir(c.contact)">
                <div class="d-flex justify-content-between">
                  <strong>{{ c.contact.fullName }}</strong>
                  @if (c.nonLus) { <span class="badge rounded-pill text-bg-danger">{{ c.nonLus }}</span> }
                </div>
                <div class="small text-truncate opacity-75">{{ c.dernierMessage.contenu }}</div>
              </button>
            } @empty { <div class="p-3 small text-muted">Aucune conversation.</div> }
          </div>
        </div>

        <!-- Fil de discussion -->
        <div class="col-md-8 d-flex flex-column" [class.d-none]="!contact()" [class.d-md-flex]="true">
          @if (contact(); as c) {
            <div class="p-2 border-bottom d-flex align-items-center gap-2">
              <button class="btn btn-sm btn-light d-md-none" (click)="contact.set(null)" aria-label="Retour"><i class="bi bi-arrow-left"></i></button>
              <strong>{{ c.fullName }}</strong><span class="small text-muted">{{ roles[c.type] }}</span>
            </div>
            <div #fil class="flex-grow-1 p-3 overflow-auto bg-light" style="max-height: 55vh">
              @for (m of messages(); track m.id) {
                <div class="d-flex mb-2" [class.justify-content-end]="m.expediteur.id === moi()">
                  <div class="p-2 px-3 rounded-3" style="max-width: 78%" [class.bg-brand]="m.expediteur.id === moi()" [class.text-white]="m.expediteur.id === moi()" [class.bg-white]="m.expediteur.id !== moi()">
                    <div style="white-space: pre-wrap">{{ m.contenu }}</div>
                    <div class="small opacity-75 text-end">{{ heure(m.dateEnvoi) }}</div>
                  </div>
                </div>
              } @empty { <div class="text-muted small text-center">Commencez la conversation.</div> }
            </div>
            <form class="p-2 border-top d-flex gap-2" (ngSubmit)="envoyer()">
              <input class="form-control" name="texte" [(ngModel)]="texte" placeholder="Écrire un message..." autocomplete="off">
              <button class="btn btn-brand" [disabled]="!texte.trim()" aria-label="Envoyer"><i class="bi bi-send"></i></button>
            </form>
          } @else {
            <div class="m-auto text-muted text-center p-4"><i class="bi bi-chat-dots fs-1"></i><div>Choisissez une conversation</div></div>
          }
        </div>
      </div>
    </div>
  `,
})
export class MessagesPage {
  private api = inject(Api);
  private auth = inject(Auth);
  private notifs = inject(NotificationsService);
  private fil = viewChild<ElementRef<HTMLElement>>('fil');

  roles: Record<string, string> = { gerant: 'Gérant', kine: 'Kiné', patient: 'Patient' };
  conversations = signal<Conversation[]>([]);
  contacts = signal<UserLight[]>([]);
  contact = signal<UserLight | null>(null);
  messages = signal<Message[]>([]);
  texte = '';
  cible = 'tous';
  diffusionTexte = '';
  erreur = signal<string | null>(null);
  succes = signal<string | null>(null);
  moi = computed(() => this.auth.user()?.id);
  estGerant = computed(() => this.auth.user()?.type === 'gerant');

  constructor() {
    this.chargerConversations();
    this.api.get<UserLight[]>('/messages/contacts').subscribe(l => this.contacts.set(l));
  }

  chargerConversations(): void {
    this.api.get<Conversation[]>('/messages').subscribe(l => this.conversations.set(l));
  }

  ouvrir(c: UserLight | null): void {
    if (!c) {
      return;
    }
    this.contact.set(c);
    this.api.get<{ messages: Message[] }>(`/messages/avec/${c.id}`).subscribe(r => {
      this.messages.set(r.messages);
      this.defiler();
      this.chargerConversations();
      this.notifs.rafraichir();
    });
  }

  envoyer(): void {
    const c = this.contact();
    if (!c || !this.texte.trim()) {
      return;
    }
    this.api.post<{ data: Message }>('/messages', { destinataireId: c.id, contenu: this.texte }).subscribe({
      next: r => {
        this.messages.update(l => [...l, r.data]);
        this.texte = '';
        this.defiler();
        this.chargerConversations();
      },
      error: e => this.erreur.set(errorMessage(e)),
    });
  }

  diffuser(): void {
    this.api.post<{ message: string }>('/messages/diffusion', { cible: this.cible, contenu: this.diffusionTexte }).subscribe({
      next: r => { this.succes.set(r.message); this.diffusionTexte = ''; this.chargerConversations(); },
      error: e => this.erreur.set(errorMessage(e)),
    });
  }

  heure(iso: string): string {
    return new Date(iso).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
  }

  private defiler(): void {
    setTimeout(() => {
      const el = this.fil()?.nativeElement;
      if (el) {
        el.scrollTop = el.scrollHeight;
      }
    });
  }
}
