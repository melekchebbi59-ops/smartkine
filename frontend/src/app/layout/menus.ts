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
    { label: 'Mon profil', icon: 'person-circle', link: '/profil' },
  ],
  kine: [
    { label: 'Accueil', icon: 'house', link: '/kine' },
    { label: 'Mon profil', icon: 'person-circle', link: '/profil' },
  ],
  patient: [
    { label: 'Accueil', icon: 'house', link: '/patient' },
    { label: 'Mon profil', icon: 'person-circle', link: '/profil' },
  ],
};
