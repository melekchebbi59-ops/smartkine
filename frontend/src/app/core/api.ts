import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL } from './config';

/** Petit service générique pour appeler l'API Symfony. */
@Injectable({ providedIn: 'root' })
export class Api {
  private http = inject(HttpClient);

  get<T>(path: string, params?: Record<string, string | number | null | undefined>): Observable<T> {
    let httpParams = new HttpParams();
    for (const [k, v] of Object.entries(params ?? {})) {
      if (v !== null && v !== undefined && v !== '') {
        httpParams = httpParams.set(k, String(v));
      }
    }
    return this.http.get<T>(API_URL + path, { params: httpParams });
  }

  post<T>(path: string, body: unknown = {}): Observable<T> {
    return this.http.post<T>(API_URL + path, body);
  }

  put<T>(path: string, body: unknown = {}): Observable<T> {
    return this.http.put<T>(API_URL + path, body);
  }

  patch<T>(path: string, body: unknown = {}): Observable<T> {
    return this.http.patch<T>(API_URL + path, body);
  }

  delete<T>(path: string): Observable<T> {
    return this.http.delete<T>(API_URL + path);
  }

  /** Envoi de fichier (image d'exercice). */
  upload<T>(path: string, form: FormData): Observable<T> {
    return this.http.post<T>(API_URL + path, form);
  }
}

/** Extrait le message d'erreur renvoyé par l'API. */
export function errorMessage(err: unknown): string {
  const e = err as { error?: { message?: string }; status?: number };
  if (e?.status === 0) {
    return "Impossible de contacter le serveur. Le backend Symfony est-il lancé ?";
  }
  return e?.error?.message ?? 'Une erreur est survenue.';
}
