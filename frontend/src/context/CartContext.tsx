import { useCallback, useEffect, useMemo, useState } from 'react';
import { useCatalogue } from './catalogue-context';
import { CartContext, lineKey, type CartLine, type CartValue } from './cart-context';

const STORAGE_KEY = 'rcc-cart';

/**
 * Le panier survit au rechargement, et se **recale** sur le catalogue.
 *
 * Il portait auparavant les seuls identifiants et reconstruisait ses lignes
 * depuis les modules du front — ce qui n'est plus possible : le catalogue
 * arrive maintenant du serveur, quelques centaines de millisecondes après
 * l'affichage. Attendre cette réponse aurait montré un panier vide à quelqu'un
 * qui en a un.
 *
 * Les lignes sont donc enregistrées entières et affichées aussitôt, puis
 * confrontées au catalogue dès son arrivée : un prix modifié se corrige, un
 * article retiré de la vente disparaît. Cet affichage n'engage rien — le
 * serveur relit ses propres prix et son propre stock au moment de la commande,
 * et c'est lui qui tranche.
 */
type StoredLine = Partial<CartLine> & { type: CartLine['type']; id: number; size: string; qty: number };

function readStorage(): CartLine[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];

    const parsed: StoredLine[] = JSON.parse(raw);

    return parsed
      .filter((stored) => typeof stored?.id === 'number' && typeof stored?.size === 'string')
      .map((stored) => ({
        // Les paniers enregistrés avant ce changement ne portent que des
        // identifiants : on les accepte tels quels, le recalage les complète.
        key: lineKey(stored.type, stored.id, stored.size),
        type: stored.type,
        id: stored.id,
        href: stored.href ?? null,
        title: stored.title ?? '',
        subtitle: stored.subtitle ?? '',
        size: stored.size,
        unit_price_xof: stored.unit_price_xof ?? 0,
        image: stored.image ?? null,
        accent: stored.accent ?? '#808080',
        qty: stored.qty,
      }));
  } catch {
    return [];
  }
}

export function CartProvider({ children }: { children: React.ReactNode }) {
  const { products, jerseys, loading } = useCatalogue();

  const [lines, setLines] = useState<CartLine[]>(readStorage);
  const [isOpen, setIsOpen] = useState(false);
  const [toast, setToast] = useState<CartValue['toast']>(null);

  // Recalage sur le catalogue, à chaque fois qu'il change.
  useEffect(() => {
    if (loading) return;

    setLines((current) =>
      current.flatMap((line) => {
        if (line.type === 'sneaker') {
          const product = products.find((item) => item.id === line.id);
          if (!product) return [];

          return [
            {
              ...line,
              href: `/boutique/${product.slug}`,
              title: `${product.brand} ${product.model}`,
              subtitle: product.colorway,
              unit_price_xof: product.price_xof,
              image: product.image,
              accent: product.accent,
            },
          ];
        }

        const jersey = jerseys.find((item) => item.id === line.id);
        if (!jersey) return [];

        return [
          {
            ...line,
            href: `/maillots/${jersey.slug}`,
            title: jersey.club,
            subtitle: `${jersey.kit} · ${jersey.season}`,
            unit_price_xof: jersey.price_xof,
            image: jersey.image,
            accent: jersey.accent,
          },
        ];
      }),
    );
  }, [products, jerseys, loading]);

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(lines));
    } catch {
      // stockage indisponible (navigation privée) — le panier ne survivra
      // simplement pas au rechargement
    }
  }, [lines]);

  const add: CartValue['add'] = useCallback((line, qty = 1) => {
    const key = lineKey(line.type, line.id, line.size);
    setLines((current) => {
      const existing = current.find((item) => item.key === key);
      if (existing) {
        return current.map((item) => (item.key === key ? { ...item, qty: item.qty + qty } : item));
      }
      return [...current, { ...line, key, qty }];
    });
    setToast({ id: Date.now(), title: line.title });
  }, []);

  // the notice clears itself; a new add replaces it rather than stacking
  useEffect(() => {
    if (!toast) return;
    const timer = window.setTimeout(() => setToast(null), 3200);
    return () => window.clearTimeout(timer);
  }, [toast]);

  const setQty: CartValue['setQty'] = useCallback((key, qty) => {
    setLines((current) =>
      qty <= 0
        ? current.filter((item) => item.key !== key)
        : current.map((item) => (item.key === key ? { ...item, qty } : item)),
    );
  }, []);

  const remove: CartValue['remove'] = useCallback((key) => {
    setLines((current) => current.filter((item) => item.key !== key));
  }, []);

  const clear = useCallback(() => setLines([]), []);
  const openCart = useCallback(() => setIsOpen(true), []);
  const closeCart = useCallback(() => setIsOpen(false), []);

  const value = useMemo<CartValue>(
    () => ({
      lines,
      count: lines.reduce((total, line) => total + line.qty, 0),
      subtotal: lines.reduce((total, line) => total + line.qty * line.unit_price_xof, 0),
      isOpen,
      toast,
      openCart,
      closeCart,
      add,
      setQty,
      remove,
      clear,
    }),
    [lines, isOpen, toast, openCart, closeCart, add, setQty, remove, clear],
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}
