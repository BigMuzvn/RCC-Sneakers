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
    throw new ApiFailure(
      response.status,
      payload?.error ?? {
        code: 'server_error',
        message: 'Une erreur est survenue. Réessayez dans un instant.',
      },
    );
  }

  return payload?.data as T;
}
