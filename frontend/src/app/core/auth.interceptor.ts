import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { Auth } from '../services/auth';

/** Ajoute le token JWT à chaque requête et déconnecte si le token a expiré. */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(Auth);
  const router = inject(Router);
  const token = auth.getToken();

  const request = token && !req.url.endsWith('/login')
    ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } })
    : req;

  return next(request).pipe(
    catchError((err) => {
      if (err.status === 401 && token && !req.url.endsWith('/login')) {
        auth.logout();
        router.navigate(['/connexion'], { queryParams: { expire: 1 } });
      }
      return throwError(() => err);
    })
  );
};
