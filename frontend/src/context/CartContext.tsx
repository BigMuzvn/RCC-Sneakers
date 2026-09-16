import { useCallback, useEffect, useMemo, useState } from 'react';
import { PRODUCTS } from '../data/products';
import { JERSEYS } from '../data/jerseys';
import { CartContext, lineKey, type CartLine, type CartValue } from './cart-context';

/** Only identifiers are persisted — image URLs are build hashes and would break after a rebuild. */
type StoredLine = { type: CartLine['type']; id: number; size: string; qty: number };

const STORAGE_KEY = 'rcc-cart';

function buildLine(stored: StoredLine): CartLine | null {
  if (stored.type === 'sneaker') {
    const product = PRODUCTS.find((item) => item.id === stored.id);
    if (!product) return null;
    return {
      key: lineKey('sneaker', product.id, stored.size),
      type: 'sneaker',
      id: product.id,
      href: `/boutique/${product.slug}`,
      title: `${product.brand} ${product.model}`,
      subtitle: product.colorway,
      size: stored.size,
      unit_price_xof: product.price_xof,
      image: product.image,
      accent: product.accent,
      qty: stored.qty,
    };
  }
  const jersey = JERSEYS.find((item) => item.id === stored.id);
  if (!jersey) return null;
  return {
    key: lineKey('jersey', jersey.id, stored.size),
    type: 'jersey',
    id: jersey.id,
    href: null,
    title: jersey.club,
    subtitle: `${jersey.kit} · ${jersey.season}`,
    size: stored.size,
    unit_price_xof: jersey.price_xof,
    image: jersey.image,
    accent: jersey.accent,
    qty: stored.qty,
  };
}

function readStorage(): CartLine[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed: StoredLine[] = JSON.parse(raw);
    return parsed.map(buildLine).filter((line): line is CartLine => line !== null);
  } catch {
    return [];
  }
}

export function CartProvider({ children }: { children: React.ReactNode }) {
  const [lines, setLines] = useState<CartLine[]>(readStorage);
  const [isOpen, setIsOpen] = useState(false);
  const [toast, setToast] = useState<CartValue['toast']>(null);

  useEffect(() => {
    try {
      const stored: StoredLine[] = lines.map(({ type, id, size, qty }) => ({ type, id, size, qty }));
      localStorage.setItem(STORAGE_KEY, JSON.stringify(stored));
    } catch {
      // storage unavailable (private mode) — the cart simply won't survive a reload
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
