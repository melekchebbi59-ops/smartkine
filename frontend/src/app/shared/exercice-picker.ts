import { Component, computed, input, model, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Exercice } from '../core/models';

/** Sélection d'exercices de la banque (cases à cocher + recherche). */
@Component({
  selector: 'app-exercice-picker',
  imports: [FormsModule],
  template: `
    <input class="form-control form-control-sm mb-2" placeholder="Rechercher un exercice..." [ngModel]="q()" (ngModelChange)="q.set($event)">
    <div class="border rounded p-2" style="max-height: 220px; overflow-y: auto">
      @for (e of filtres(); track e.id) {
        <div class="form-check">
          <input class="form-check-input" type="checkbox" [id]="'exo' + uid + e.id" [checked]="selected().includes(e.id)" (change)="toggle(e.id)">
          <label class="form-check-label small" [for]="'exo' + uid + e.id">
            {{ e.nom }} <span class="text-muted">· {{ e.duree }} min · {{ e.cout }} DT</span>
          </label>
        </div>
      } @empty {
        <div class="small text-muted">Aucun exercice dans la banque.</div>
      }
    </div>
    <div class="small text-muted mt-1">{{ selected().length }} exercice(s) sélectionné(s)</div>
  `,
})
export class ExercicePicker {
  exercices = input<Exercice[]>([]);
  selected = model<number[]>([]);
  q = signal('');
  uid = Math.random().toString(36).slice(2, 7);

  filtres = computed(() => {
    const q = this.q().toLowerCase();
    return this.exercices().filter(e => !q || e.nom.toLowerCase().includes(q) || (e.objectif ?? '').toLowerCase().includes(q));
  });

  toggle(id: number): void {
    const s = this.selected();
    this.selected.set(s.includes(id) ? s.filter(x => x !== id) : [...s, id]);
  }
}
