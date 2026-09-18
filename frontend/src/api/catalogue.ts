import { api } from './client';

/**
 * Le catalogue, tel que le serveur le donne.
 *
 * Ces types remplacent les modules `src/data/` : le catalogue n'est plus écrit
 * dans le front, il est lu. C'est ce qui donne son sens à l'administration —
 * une paire ajoutée là-bas apparaît ici, et un stock épuisé cesse d'être
 * proposé sans redéploiement.
 *
 * Deux conversions seulement séparent la réponse de ce qu'attendent les
 * composants, et elles sont faites ici pour n'exister qu'une fois.
 */

export type Category = 'lifestyle' | 'running' | 'basketball';

export const CATEGORIES: { id: Category; label: string }[] = [
  { id: 'lifestyle', label: 'Lifestyle' },
  { id: 'running', label: 'Running' },
  { id: 'basketball', label: 'Basketball' },
];

/**
 * Une taille est une **chaîne**, pas un nombre. La base la stocke ainsi, et le
 * gérant doit pouvoir saisir « 42,5 » ou « XL » sans que le front la convertisse
 * en `NaN`. Rien n'en fait de l'arithmétique : elle est affichée et comparée.
 */
export type Variant = { size: string; stock: number };

export type Product = {
  id: number;
  slug: string;
  brand: string;
  model: string;
  sku: string | null;
  category: Category;
  gender: 'homme' | 'femme' | 'unisexe';
  colorway: string;
  description: string;
  price_xof: number;
  old_price_xof: number | null;
  is_new_drop: boolean;
  /** URL prête à poser dans un `src`, ou `null` si la paire n'a pas de rendu. */
  image: string | null;
  accent: string;
  variants: Variant[];
};

export type Jersey = {
  id: number;
  slug: string;
  club: string;
  league: string;
  brand: string;
  kit: string;
  season: string;
  colorway: string;
  price_xof: number;
  old_price_xof: number | null;
  is_new_drop: boolean;
  image: string | null;
  accent: string;
  variants: Variant[];
};

export type ShopSettings = {
  shop_city: string;
  shop_phone: string;
  shop_email: string;
  shop_hours: string;
  social_instagram: string;
  social_facebook: string;
  social_whatsapp: string;
};

/**
 * Le serveur renvoie un **nom de fichier**, pas une adresse : il ignore sous
 * quel chemin l'API est montée. La composer ici évite de la recopier dans
 * chaque composant, et de devoir tous les reprendre si elle change.
 */
export function imageUrl(name: string | null): string | null {
  return name === null || name === '' ? null : `/api/uploads/${name}`;
}

type RawProduct = Omit<Product, 'image'> & { image: string | null };
type RawJersey = Omit<Jersey, 'image'> & { image: string | null };

const withImage = <T extends { image: string | null }>(row: T): T => ({
  ...row,
  image: imageUrl(row.image),
});

export async function fetchProducts(): Promise<Product[]> {
  const { products } = await api<{ products: RawProduct[] }>('/products');

  return products.map(withImage);
}

export async function fetchJerseys(): Promise<Jersey[]> {
  const { jerseys } = await api<{ jerseys: RawJersey[] }>('/jerseys');

  return jerseys.map(withImage);
}

export async function fetchShop(): Promise<{ settings: ShopSettings; featured: string[] }> {
  return api<{ settings: ShopSettings; featured: string[] }>('/shop');
}

/**
 * Marques et championnats **déduits du catalogue**, jamais listés à la main.
 * Une marque saisie dans l'administration doit apparaître dans les filtres le
 * jour même ; une liste figée l'aurait rendue invisible tout en la vendant.
 */
export const brandsOf = (products: Product[]): string[] =>
  [...new Set(products.map((product) => product.brand))].sort((a, b) => a.localeCompare(b, 'fr'));

export const leaguesOf = (jerseys: Jersey[]): string[] =>
  [...new Set(jerseys.map((jersey) => jersey.league))].sort((a, b) => a.localeCompare(b, 'fr'));
