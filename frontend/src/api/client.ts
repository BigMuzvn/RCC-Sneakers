/**
 * Client d'API.
 *
 * `credentials: 'include'` sur chaque appel : le cookie de session est en
 * HttpOnly, donc illisible en JavaScript — c'est le navigateur qui le joint,
 * et il ne le fait pas de lui-même sur un fetch.
 */

export type ApiError = {
  code: string;
  message: string;
  /** Erreurs par champ de formulaire, à afficher sous l'input concerné. */
  fields?: Record<string, string>;
  /** Secondes avant de pouvoir réessayer, sur une réponse 429. */
  retry_after?: number;
};

/** Erreur portant la réponse du serveur, pour que l'appelant sache quoi afficher. */
export class ApiFailure extends Error {
  readonly status: number;
  readonly error: ApiError;

  constructor(status: number, error: ApiError) {
    super(error.message);
    this.name = 'ApiFailure';
    this.status = status;
    this.error = error;
  }

  get fields(): Record<string, string> {
    return this.error.fields ?? {};
  }
}

type Options = {
  method?: 'GET' | 'POST';
  body?: unknown;
  signal?: AbortSignal;
};

/**
 * Routes dont un 401 est une réponse normale et non la perte d'une session :
 * `/auth/me` est la sonde qu'on lance à chaque chargement, et les formulaires
 * d'identification répondent 401 quand les identifiants sont faux.
 */
const EXPECTED_401 = ['/auth/me', '/auth/login', '/auth/register', '/auth/logout'];

let onSessionLost: (() => void) | null = null;

/**
 * Prévenu quand le serveur déclare une session invalide alors que l'interface
 * la croyait ouverte.
 *
 * Le cookie est en HttpOnly : le JavaScript ne peut pas savoir qu'il a expiré,
 * qu'il a été révoqué depuis un autre appareil, ou que le serveur a été
 * redéployé. Le seul signal disponible est un 401 sur un appel qui n'aurait pas
 * dû en produire — sans quoi l'application continue d'afficher un client
 * connecté et laisse les erreurs surgir au pire endroit, comme au moment de
 * confirmer une commande.
 */
export function setSessionLostHandler(handler: (() => void) | null): void {
  onSessionLost = handler;
}

export async function api<T>(path: string, options: Options = {}): Promise<T> {
  const { method = 'GET', body, signal } = options;

  let response: Response;

  try {
    response = await fetch(`/api${path}`, {
      method,
      credentials: 'include',
      signal,
      headers: body === undefined ? undefined : { 'Content-Type': 'application/json' },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch {
    // Coupure réseau, serveur éteint, requête annulée : le serveur n'a rien dit,
    // donc on ne prétend pas rapporter son message.
    throw new ApiFailure(0, {
      code: 'network_error',
      message: 'Connexion impossible. Vérifiez votre réseau et réessayez.',
    });
  }

  if (response.status === 204) {
    return undefined as T;
  }

  const payload = await response.json().catch(() => null);

  if (!response.ok) {
    if (response.status === 401 && !EXPECTED_401.includes(path)) {
      onSessionLost?.();
    }

    if (payload?.error) {
      throw new ApiFailure(response.status, payload.error);
    }

    // Réponse sans enveloppe JSON : ce n'est pas l'API qui a répondu, mais
    // quelque chose qui s'est interposé — proxy de développement dont la cible
    // est éteinte, page d'erreur de l'hébergeur. Le distinguer d'une vraie
    // erreur applicative évite de conseiller « réessayez » quand réessayer ne
    // servira jamais à rien.
    const gateway = response.status >= 502 && response.status <= 504;

    throw new ApiFailure(response.status, {
      code: gateway ? 'api_unreachable' : 'server_error',
      message: gateway
        ? import.meta.env.DEV
          ? "L'API ne répond pas. Le serveur PHP est-il démarré ? php -S localhost:8000 -t backend/public"
          : 'Le service est momentanément indisponible. Réessayez dans quelques minutes.'
        : 'Une erreur est survenue. Réessayez dans un instant.',
    });
  }

  return payload?.data as T;
}
