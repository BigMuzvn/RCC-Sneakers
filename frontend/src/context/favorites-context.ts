import { createContext, useContext } from 'react';

export type FavoriteType = 'sneaker' | 'jersey';

export type Favorite = { item_type: FavoriteType; item_id: number; created_at: string };

export type FavoritesValue = {
  favorites: Favorite[];
  loading: boolean;
  isFavorite: (type: FavoriteType, id: number) => boolean;
  /** Renvoie le nouvel état, ou `null` si personne n'est connecté. */
  toggle: (type: FavoriteType, id: number) => Promise<boolean | null>;
};

export const FavoritesContext = createContext<FavoritesValue | null>(null);

export const favoriteKey = (type: FavoriteType, id: number) => `${type}-${id}`;

export function useFavorites() {
  const context = useContext(FavoritesContext);
  if (!context) throw new Error('useFavorites doit être utilisé dans un FavoritesProvider');
  return context;
}
