import { Component, input } from '@angular/core';

/** Affiche un message de succès / d'erreur. */
@Component({
  selector: 'app-alert',
  template: `
    @if (error()) {
      <div class="alert alert-danger py-2" role="alert"><i class="bi bi-exclamation-triangle me-1"></i>{{ error() }}</div>
    }
    @if (success()) {
      <div class="alert alert-success py-2" role="status"><i class="bi bi-check-circle me-1"></i>{{ success() }}</div>
    }
  `,
})
export class Alert {
  error = input<string | null>(null);
  success = input<string | null>(null);
}
