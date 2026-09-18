import { createContext, useContext } from 'react';
import type { Jersey, Product, ShopSettings } from '../api/catalogue';

export type CatalogueValue = {
  products: Product[];
  jerseys: Jersey[];
  /** Coordonnées de la boutique, modifiables depuis les réglages. */
  settings: ShopSettings;
  /** Slugs des paires mises en avant sur l'accueil, dans l'ordre choisi. */
  featured: string[];
  /**
   * Vrai tant que le catalogue n'est pas arrivé. Sans cet état, une fiche
   * produit conclurait « ce modèle n'existe pas » pendant la fraction de
   * seconde qui précède la réponse.
   */
  loading: boolean;
  /** Message affichable si le serveur n'a rien pu donner. */
  error: string;
  reload: () => void;
};

export const EMPTY_SETTINGS: ShopSettings = {
  shop_city: '',
  shop_phone: '',
  shop_email: '',
  shop_hours: '',
  social_instagram: '',
  social_facebook: '',
  social_whatsapp: '',
};

export const CatalogueContext = createContext<CatalogueValue | null>(null);

export function useCatalogue() {
  const context = useContext(CatalogueContext);
  if (!context) throw new Error('useCatalogue doit être utilisé dans un CatalogueProvider');
  return context;
}
