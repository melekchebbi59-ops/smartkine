import { Routes } from '@angular/router';
import { guestGuard, roleGuard } from './core/role.guard';
import { AppLayout } from './layout/app-layout';
import { PublicLayout } from './layout/public-layout';
import { Login } from './pages/login/login';

export const routes: Routes = [
  // ----- Espace visiteur -----
  {
    path: '',
    component: PublicLayout,
    children: [
      { path: '', loadComponent: () => import('./pages/public/home').then(m => m.Home), title: 'SmartKiné' },
      { path: 'rendez-vous', loadComponent: () => import('./pages/public/demande-rdv').then(m => m.DemandeRdvPage), title: 'Prendre rendez-vous' },
      { path: 'inscription', canActivate: [guestGuard], loadComponent: () => import('./pages/public/register').then(m => m.Register), title: 'Créer mon compte' },
    ],
  },
  { path: 'connexion', component: Login, canActivate: [guestGuard], title: 'Connexion' },
  { path: 'login', redirectTo: 'connexion' },
  { path: 'register', redirectTo: 'inscription' },

  // ----- Espaces connectés -----
  {
    path: '',
    component: AppLayout,
    children: [
      { path: 'profil', loadComponent: () => import('./pages/shared-pages/profil').then(m => m.Profil), title: 'Mon profil' },
      {
        path: 'gerant',
        canActivate: [roleGuard('gerant')],
        children: [
          { path: '', loadComponent: () => import('./pages/gerant/dashboard').then(m => m.GerantDashboard), title: 'Tableau de bord' },
          { path: 'demandes', loadComponent: () => import('./pages/gerant/demandes').then(m => m.GerantDemandes), title: 'Demandes de RDV' },
          { path: 'inscriptions', loadComponent: () => import('./pages/gerant/inscriptions').then(m => m.GerantInscriptions), title: 'Inscriptions' },
          { path: 'utilisateurs', loadComponent: () => import('./pages/gerant/utilisateurs').then(m => m.GerantUtilisateurs), title: 'Utilisateurs' },
        ],
      },
      {
        path: 'kine',
        canActivate: [roleGuard('kine')],
        children: [
          { path: '', loadComponent: () => import('./pages/kine/accueil').then(m => m.KineAccueil), title: 'Espace kiné' },
        ],
      },
      {
        path: 'patient',
        canActivate: [roleGuard('patient')],
        children: [
          { path: '', loadComponent: () => import('./pages/patient/accueil').then(m => m.PatientAccueil), title: 'Espace patient' },
        ],
      },
    ],
  },

  { path: '**', redirectTo: '' },
];
