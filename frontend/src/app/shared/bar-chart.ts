import { Component, computed, input } from '@angular/core';

export interface BarDatum { label: string; value: number; }

/**
 * Petit graphique en barres (une seule série, sans librairie) :
 * barres fines à bouts arrondis, grille discrète, valeur au survol (title).
 */
@Component({
  selector: 'app-bar-chart',
  template: `
    <div class="bar-chart" [style.height.px]="height()">
      <div class="bars">
        @for (d of data(); track d.label) {
          <div class="col-bar" [title]="d.label + ' : ' + format()(d.value)">
            <div class="bar" [style.height.%]="max() ? (d.value / max()) * 100 : 0"></div>
          </div>
        }
      </div>
    </div>
    <div class="labels">
      @for (d of data(); track d.label; let i = $index) {
        <span>{{ i % labelEvery() === 0 ? d.label : '' }}</span>
      }
    </div>
    <div class="small text-muted mt-1">Max : {{ format()(max()) }}</div>
  `,
  styles: `
    .bar-chart { border-bottom: 1px solid #cfd8dc; background-image: linear-gradient(#eef2f3 1px, transparent 1px); background-size: 100% 25%; }
    .bars { display: flex; align-items: flex-end; gap: 2px; height: 100%; }
    .col-bar { flex: 1; height: 100%; display: flex; align-items: flex-end; cursor: default; }
    .col-bar:hover .bar { background: var(--brand-dark); }
    .bar { width: 100%; max-width: 28px; margin: 0 auto; min-height: 2px; background: var(--brand); border-radius: 4px 4px 0 0; transition: height .3s; }
    .labels { display: flex; gap: 2px; font-size: .7rem; color: #78909c; }
    .labels span { flex: 1; text-align: center; white-space: nowrap; overflow: visible; }
  `,
})
export class BarChart {
  data = input<BarDatum[]>([]);
  height = input(160);
  labelEvery = input(1);
  format = input<(v: number) => string>(v => String(v));
  max = computed(() => Math.max(0, ...this.data().map(d => d.value)));
}
