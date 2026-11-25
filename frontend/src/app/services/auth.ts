import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { API_URL } from '../core/config';
import { UserType } from '../core/models';

export interface RegisterData {
  firstName: string;
  lastName: string;
  email: string;
  password: string;
  phone?: string;
  dateNaissance?: string;
  adresse?: string;
  qualification?: string;
  medecinTraitant?: string;
  typeTraitement?: string;
}

interface LoginData {
  email: string;
  password: string;
}

interface AuthResponse {
  token: string;
}

/** Infos lues dans le token JWT (ajoutées par JwtCreatedListener côté Symfony). */
export interface TokenUser {
  id: number;
  type: UserType;
  firstName: string;
  lastName: string;
  username: string;
  roles: string[];
  exp: number;
}

@Injectable({
  providedIn: 'root'
})
export class Auth {
  private http = inject(HttpClient);
  private apiUrl = API_URL;

  /** Utilisateur connecté (null si déconnecté). */
  readonly user = signal<TokenUser | null>(this.decode(localStorage.getItem('token')));
  readonly isAuthenticated = computed(() => this.user() !== null);

  // Inscription d'un patient (après son 1er RDV)
  register(data: RegisterData): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${this.apiUrl}/register`, data);
  }

  // Connexion (patient, kiné ou gérant)
  login(data: LoginData): Observable<AuthResponse> {
    return this.http.post<AuthResponse>(`${this.apiUrl}/login`, data).pipe(
      tap(response => {
        localStorage.setItem('token', response.token);
        this.user.set(this.decode(response.token));
      })
    );
  }

  // Récupérer le token
  getToken(): string | null {
    return this.isLoggedIn() ? localStorage.getItem('token') : null;
  }

  // Déconnexion
  logout(): void {
    localStorage.removeItem('token');
    this.user.set(null);
  }

  // Vérifier si l'utilisateur est connecté (et que le token n'a pas expiré)
  isLoggedIn(): boolean {
    const u = this.user();
    if (!u) {
      return false;
    }
    if (u.exp * 1000 < Date.now()) {
      this.logout();
      return false;
    }
    return true;
  }

  /** Page d'accueil selon le rôle. */
  homeUrl(): string {
    switch (this.user()?.type) {
      case 'gerant': return '/gerant';
      case 'kine': return '/kine';
      case 'patient': return '/patient';
      default: return '/';
    }
  }

  private decode(token: string | null): TokenUser | null {
    if (!token) {
      return null;
    }
    try {
      const payload = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
      const json = decodeURIComponent(
        atob(payload).split('').map(c => '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2)).join('')
      );
      return JSON.parse(json) as TokenUser;
    } catch {
      return null;
    }
  }
}
