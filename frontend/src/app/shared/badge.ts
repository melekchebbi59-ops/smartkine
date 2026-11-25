import { Component, computed, input } from '@angular/core';
import { STATUTS_DEMANDE, STATUTS_FACTURE, STATUTS_SEANCE } from './format';

/** Badge coloré pour un statut : <app-badge [statut]="s.statut" kind="seance" /> */
@Component({
  selector: 'app-badge',
  template: `<span class="badge rounded-pill {{ info().css }}">{{ info().label }}</span>`,
})
export class Badge {
  statut = input.required<string>();
  kind = input<'seance' | 'demande' | 'facture'>('seance');

  info = computed(() => {
    const table = this.kind() === 'seance' ? STATUTS_SEANCE : this.kind() === 'facture' ? STATUTS_FACTURE : STATUTS_DEMANDE;
    return table[this.statut()] ?? { label: this.statut(), css: 'text-bg-light' };
  });
}
