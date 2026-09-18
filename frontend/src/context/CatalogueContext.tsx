import { useCallback, useEffect, useMemo, useState } from 'react';
import { fetchJerseys, fetchProducts, fetchShop, type Jersey, type Product, type ShopSettings } from '../api/catalogue';
import { CatalogueContext, EMPTY_SETTINGS, type CatalogueValue } from './catalogue-context';
import { retirerChargement } from '../chargement';

/**
 * Le catalogue, chargé une fois pour toute la visite.
 *
 * Trois appels en parallèle plutôt qu'un seul gros : ce sont trois routes
 * publiques déjà écrites et testées, et le navigateur les mène de front. Les
 * regrouper aurait imposé une quatrième route à maintenir pour rien.
 *
 * Il vit au-dessus du panier, qui en dépend pour retrouver ce que ses lignes
 * désignent.
 */
export function CatalogueProvider({ children }: { children: React.ReactNode }) {
  const [products, setProducts] = useState<Product[]>([]);
  const [jerseys, setJerseys] = useState<Jersey[]>([]);
  const [settings, setSettings] = useState<ShopSettings>(EMPTY_SETTINGS);
  const [featured, setFeatured] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(() => {
    setLoading(true);
    setError('');

    Promise.all([fetchProducts(), fetchJerseys(), fetchShop()])
      .then(([nouveauxProduits, nouveauxMaillots, boutique]) => {
        setProducts(nouveauxProduits);
        setJerseys(nouveauxMaillots);
        setSettings(boutique.settings);
        setFeatured(boutique.featured);
      })
      .catch(() => {
        // Le message reste sobre : le visiteur ne peut rien faire du détail
        // technique, et la boutique doit rester présentable même vide.
        setError("Le catalogue n'a pas pu être chargé. Vérifiez votre connexion, puis réessayez.");
      })
      .finally(() => {
        setLoading(false);

        // L'écran de chargement s'efface ici, et non au montage de React : il
        // couvre l'attente jusqu'à ce que la boutique ait quelque chose à
        // montrer. Y compris en cas d'échec — laisser tourner une barre devant
        // quelqu'un dont la connexion a coupé ne lui apprend rien, alors que la
        // page, elle, sait le dire et propose de réessayer.
        retirerChargement();
      });
  }, []);

  useEffect(load, [load]);

  const value = useMemo<CatalogueValue>(
    () => ({ products, jerseys, settings, featured, loading, error, reload: load }),
    [products, jerseys, settings, featured, loading, error, load],
  );

  return <CatalogueContext.Provider value={value}>{children}</CatalogueContext.Provider>;
}
