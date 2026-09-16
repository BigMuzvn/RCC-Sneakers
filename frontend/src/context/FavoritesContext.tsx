import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react';
import { api } from '../api/client';
import { useAuth } from './auth-context';
import {
  FavoritesContext,
  favoriteKey,
  type Favorite,
  type FavoritesValue,
  type FavoriteType,
} from './favorites-context';

export function FavoritesProvider({ children }: { children: ReactNode }) {
  const { customer } = useAuth();
  const [favorites, setFavorites] = useState<Favorite[]>([]);
  const [loading, setLoading] = useState(false);

  // Les favoris appartiennent au compte : ils sont chargés à la connexion et
  // effacés à la déconnexion, sinon ceux d'un client resteraient affichés au
  // suivant sur un appareil partagé — cas courant ici.
  useEffect(() => {
    if (!customer) {
      setFavorites([]);
      return;
    }

    let cancelled = false;
    setLoading(true);

    api<{ favorites: Favorite[] }>('/favorites')
      .then((data) => {
        if (!cancelled) setFavorites(data.favorites);
      })
      .catch(() => {
        if (!cancelled) setFavorites([]);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [customer]);

  const keys = useMemo(
    () => new Set(favorites.map((f) => favoriteKey(f.item_type, f.item_id))),
    [favorites],
  );

  const isFavorite = useCallback(
    (type: FavoriteType, id: number) => keys.has(favoriteKey(type, id)),
    [keys],
  );

  const toggle = useCallback<FavoritesValue['toggle']>(
    async (type, id) => {
      if (!customer) return null;

      const wasFavorite = keys.has(favoriteKey(type, id));

      // Bascule optimiste : le cœur réagit au doigt, pas à la latence du
      // réseau. En cas d'échec l'état d'origine est remis ci-dessous.
      setFavorites((current) =>
        wasFavorite
          ? current.filter((f) => !(f.item_type === type && f.item_id === id))
          : [{ item_type: type, item_id: id, created_at: new Date().toISOString() }, ...current],
      );

      try {
        const data = await api<{ favorited: boolean }>('/favorites/toggle', {
          method: 'POST',
          body: { item_type: type, item_id: id },
        });
        return data.favorited;
      } catch {
        setFavorites((current) =>
          wasFavorite
            ? [{ item_type: type, item_id: id, created_at: new Date().toISOString() }, ...current]
            : current.filter((f) => !(f.item_type === type && f.item_id === id)),
        );
        return wasFavorite;
      }
    },
    [customer, keys],
  );

  const value = useMemo<FavoritesValue>(
    () => ({ favorites, loading, isFavorite, toggle }),
    [favorites, loading, isFavorite, toggle],
  );

  return <FavoritesContext.Provider value={value}>{children}</FavoritesContext.Provider>;
}
