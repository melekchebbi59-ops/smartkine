import { Injectable, inject, signal } from '@angular/core';
import { Api } from './api';
import { Notification } from './models';

/** US-12 : notifications + compteur de messages non lus (rafraîchis toutes les 30 s). */
@Injectable({ providedIn: 'root' })
export class NotificationsService {
  private api = inject(Api);
  private timer: ReturnType<typeof setInterval> | null = null;

  readonly notifications = signal<Notification[]>([]);
  readonly nonLues = signal(0);
  readonly messagesNonLus = signal(0);

  demarrer(): void {
    this.rafraichir();
    if (!this.timer) {
      this.timer = setInterval(() => this.rafraichir(), 30000);
    }
  }

  arreter(): void {
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
    this.notifications.set([]);
    this.nonLues.set(0);
    this.messagesNonLus.set(0);
  }

  rafraichir(): void {
    this.api.get<{ nonLues: number; notifications: Notification[] }>('/notifications').subscribe({
      next: r => { this.notifications.set(r.notifications); this.nonLues.set(r.nonLues); },
      error: () => {},
    });
    this.api.get<{ count: number }>('/messages/nonlus').subscribe({
      next: r => this.messagesNonLus.set(r.count),
      error: () => {},
    });
  }

  toutLire(): void {
    this.api.post('/notifications/lire-tout').subscribe(() => {
      this.nonLues.set(0);
      this.notifications.update(l => l.map(n => ({ ...n, lue: true })));
    });
  }
}
