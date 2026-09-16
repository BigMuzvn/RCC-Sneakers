import { createContext, useContext } from 'react';

export type CartLine = {
  key: string;
  type: 'sneaker' | 'jersey';
  id: number;
  href: string | null;
  title: string;
  subtitle: string;
  size: string;
  unit_price_xof: number;
  image: string | null;
  accent: string;
  qty: number;
};

export type CartValue = {
  lines: CartLine[];
  count: number;
  subtotal: number;
  isOpen: boolean;
  /** Transient "added to cart" notice — adding never opens the drawer, so several items can be added in a row. */
  toast: { id: number; title: string } | null;
  openCart: () => void;
  closeCart: () => void;
  add: (line: Omit<CartLine, 'key' | 'qty'>, qty?: number) => void;
  setQty: (key: string, qty: number) => void;
  remove: (key: string) => void;
  clear: () => void;
};

export const CartContext = createContext<CartValue | null>(null);

export const lineKey = (type: CartLine['type'], id: number, size: string) => `${type}-${id}-${size}`;

export function useCart() {
  const context = useContext(CartContext);
  if (!context) throw new Error('useCart doit être utilisé dans un CartProvider');
  return context;
}
