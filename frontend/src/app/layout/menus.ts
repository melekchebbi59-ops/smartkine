import { UserType } from '../core/models';

export interface MenuItem {
  label: string;
  icon: string;
  link: string;
}

/** Menu latéral de chaque rôle. */
export const MENUS: Record<UserType, MenuItem[]> = {
  gerant: [
    { label: 'Tableau de bord', icon: 'speedometer2', link: '/gerant' },
    { label: 'Demandes de RDV', icon: 'calendar-plus', link: '/gerant/demandes' },
    { label: 'Inscriptions', icon: 'person-check', link: '/gerant/inscriptions' },
    { label: 'Utilisateurs', icon: 'people', link: '/gerant/utilisateurs' },
    { label: 'Patients & affectations', icon: 'diagram-3', link: '/gerant/patients' },
    { label: "Banque d'exercices", icon: 'collection', link: '/gerant/exercices' },
    { label: 'Mon profil', icon: 'person-circle', link: '/profil' },
  ],
  kine: [
    { label: 'Accueil', icon: 'house', link: '/kine' },
    { label: 'Mon agenda', icon: 'calendar-week', link: '/kine/agenda' },
    { label: 'Demandes patients', icon: 'arrow-left-right', link: '/kine/demandes' },
    { label: 'Mes patients', icon: 'people', link: '/kine/patients' },
    { label: "Banque d'exercices", icon: 'collection', link: '/kine/exercices' },
    { label: 'Mon profil', icon: 'person-circle', link: '/profil' },
  ],
  patient: [
    { label: 'Accueil', icon: 'house', link: '/patient' },
    { label: 'Mon planning', icon: 'calendar-week', link: '/patient/planning' },
    { label: 'Mon plan de rééducation', icon: 'journal-medical', link: '/patient/plan' },
    { label: 'Mon profil', icon: 'person-circle', link: '/profil' },
  ],
};
