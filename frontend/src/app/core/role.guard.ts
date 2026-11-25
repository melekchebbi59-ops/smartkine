import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { Auth } from '../services/auth';
import { UserType } from './models';

/** Protège une route : il faut être connecté avec le bon rôle. */
export function roleGuard(type: UserType): CanActivateFn {
  return () => {
    const auth = inject(Auth);
    const router = inject(Router);

    if (!auth.isLoggedIn()) {
      return router.createUrlTree(['/connexion']);
    }
    if (auth.user()?.type !== type) {
      return router.createUrlTree([auth.homeUrl()]);
    }
    return true;
  };
}

/** Pages réservées aux visiteurs non connectés (connexion, inscription). */
export const guestGuard: CanActivateFn = () => {
  const auth = inject(Auth);
  return auth.isLoggedIn() ? inject(Router).createUrlTree([auth.homeUrl()]) : true;
};
