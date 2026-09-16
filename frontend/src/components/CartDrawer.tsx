import { useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Minus, Plus, ShoppingBag, Trash2, X } from 'lucide-react';
import { useCart } from '../context/cart-context';
import { formatXof } from '../utils/format';

export default function CartDrawer() {
  const { lines, count, subtotal, isOpen, closeCart, setQty, remove } = useCart();
  const navigate = useNavigate();

  useEffect(() => {
    if (!isOpen) return;
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') closeCart();
    };
    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = '';
    };
  }, [isOpen, closeCart]);

  return (
    <>
      <div
        aria-hidden="true"
        onClick={closeCart}
        className={`fixed inset-0 z-[60] bg-black/70 transition-opacity duration-300 motion-reduce:transition-none ${
          isOpen ? 'opacity-100' : 'pointer-events-none opacity-0'
        }`}
      />

      <aside
        role="dialog"
        aria-modal="true"
        aria-label="Panier"
        aria-hidden={!isOpen}
        className={`fixed right-0 top-0 z-[70] flex h-[100svh] w-full max-w-[420px] flex-col border-l border-white/10 bg-[#0B0C0E] transition-transform duration-300 ease-out motion-reduce:transition-none ${
          isOpen ? 'translate-x-0' : 'translate-x-full'
        }`}
      >
        <header className="flex shrink-0 items-center justify-between border-b border-white/10 px-5 py-5">
          <h2 className="text-[11px] font-bold uppercase tracking-[0.2em] text-[#EDEFF2]">
            Panier {count > 0 && <span className="text-white/45">({count})</span>}
          </h2>
          <button
            type="button"
            onClick={closeCart}
            aria-label="Fermer le panier"
            tabIndex={isOpen ? 0 : -1}
            className="text-white/70 transition-colors hover:text-white"
          >
            <X className="h-5 w-5" strokeWidth={2} />
          </button>
        </header>

        {lines.length === 0 ? (
          <div className="flex flex-1 flex-col items-center justify-center gap-4 px-8 text-center">
            <span className="flex h-14 w-14 items-center justify-center rounded-full border border-white/15 text-white/45">
              <ShoppingBag className="h-6 w-6" strokeWidth={1.8} />
            </span>
            <p className="text-[12px] font-bold uppercase tracking-[0.12em] text-[#EDEFF2]">Votre panier est vide</p>
            <p className="text-[11px] leading-[1.7] text-white/50">
              Parcourez la boutique et ajoutez une paire pour commencer.
            </p>
            <Link
              to="/boutique"
              onClick={closeCart}
              tabIndex={isOpen ? 0 : -1}
              className="mt-2 border border-white px-6 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-white transition-colors hover:bg-white hover:text-[#141516]"
            >
              Voir la boutique
            </Link>
          </div>
        ) : (
          <>
            <ul className="flex-1 overflow-y-auto px-5 py-5">
              {lines.map((line) => (
                <li key={line.key} className="flex gap-3.5 border-b border-white/10 py-4 first:pt-0 last:border-0">
                  <div className="relative h-[72px] w-[72px] shrink-0 overflow-hidden border border-white/10">
                    <span
                      aria-hidden="true"
                      className="absolute inset-0"
                      style={{
                        background: `radial-gradient(ellipse 75% 70% at 50% 55%, ${line.accent}70 0%, ${line.accent}25 45%, transparent 75%)`,
                      }}
                    />
                    {line.image && (
                      <img
                        src={line.image}
                        alt=""
                        className="absolute inset-0 h-full w-full scale-105 object-contain p-1"
                      />
                    )}
                  </div>

                  <div className="flex min-w-0 flex-1 flex-col">
                    <div className="flex items-start justify-between gap-2">
                      <div className="min-w-0">
                        {line.href ? (
                          <Link
                            to={line.href}
                            onClick={closeCart}
                            tabIndex={isOpen ? 0 : -1}
                            className="block truncate text-[12px] font-bold uppercase tracking-[0.06em] text-[#EDEFF2] transition-colors hover:text-white"
                          >
                            {line.title}
                          </Link>
                        ) : (
                          <p className="truncate text-[12px] font-bold uppercase tracking-[0.06em] text-[#EDEFF2]">
                            {line.title}
                          </p>
                        )}
                        <p className="mt-0.5 truncate text-[10px] text-white/45">{line.subtitle}</p>
                        <p className="mt-0.5 text-[10px] uppercase tracking-[0.12em] text-white/60">
                          Taille {line.size}
                        </p>
                      </div>
                      <button
                        type="button"
                        onClick={() => remove(line.key)}
                        aria-label={`Retirer ${line.title} du panier`}
                        tabIndex={isOpen ? 0 : -1}
                        className="shrink-0 text-white/35 transition-colors hover:text-white"
                      >
                        <Trash2 className="h-4 w-4" strokeWidth={1.8} />
                      </button>
                    </div>

                    <div className="mt-2.5 flex items-center justify-between gap-2">
                      <div className="flex items-center border border-white/15">
                        <button
                          type="button"
                          onClick={() => setQty(line.key, line.qty - 1)}
                          aria-label="Diminuer la quantité"
                          tabIndex={isOpen ? 0 : -1}
                          className="px-2 py-1.5 text-white/70 transition-colors hover:text-white"
                        >
                          <Minus className="h-3 w-3" strokeWidth={2.4} />
                        </button>
                        <span className="min-w-[26px] text-center text-[11px] font-bold text-[#EDEFF2]">
                          {line.qty}
                        </span>
                        <button
                          type="button"
                          onClick={() => setQty(line.key, line.qty + 1)}
                          aria-label="Augmenter la quantité"
                          tabIndex={isOpen ? 0 : -1}
                          className="px-2 py-1.5 text-white/70 transition-colors hover:text-white"
                        >
                          <Plus className="h-3 w-3" strokeWidth={2.4} />
                        </button>
                      </div>
                      <span className="font-display text-[13px] text-[#EDEFF2]">
                        {formatXof(line.unit_price_xof * line.qty)}
                      </span>
                    </div>
                  </div>
                </li>
              ))}
            </ul>

            <footer className="shrink-0 border-t border-white/10 px-5 py-5">
              <div className="flex items-baseline justify-between">
                <span className="text-[11px] font-bold uppercase tracking-[0.16em] text-white/60">Sous-total</span>
                <span className="font-display text-[20px] text-[#EDEFF2]">{formatXof(subtotal)}</span>
              </div>
              <p className="mt-1.5 text-[10px] text-white/40">Frais de livraison calculés à l'étape suivante.</p>

              <button
                type="button"
                onClick={() => {
                  closeCart();
                  navigate('/checkout');
                }}
                tabIndex={isOpen ? 0 : -1}
                className="mt-4 w-full bg-white px-6 py-3.5 text-[11px] font-bold uppercase tracking-[0.14em] text-[#141516] transition-opacity hover:opacity-90"
              >
                Procéder au paiement
              </button>
              <button
                type="button"
                onClick={closeCart}
                tabIndex={isOpen ? 0 : -1}
                className="mt-2 w-full px-6 py-2 text-[10px] uppercase tracking-[0.14em] text-white/50 transition-colors hover:text-white"
              >
                Continuer mes achats
              </button>
            </footer>
          </>
        )}
      </aside>
    </>
  );
}
