import { Check } from 'lucide-react';
import { useCart } from '../context/cart-context';

export default function CartToast() {
  const { toast, openCart } = useCart();

  return (
    <div
      aria-live="polite"
      className={`pointer-events-none fixed inset-x-4 bottom-5 z-[55] flex justify-center transition-all duration-300 ease-out motion-reduce:transition-none sm:inset-x-auto sm:right-6 sm:justify-end ${
        toast ? 'translate-y-0 opacity-100' : 'translate-y-3 opacity-0'
      }`}
    >
      {toast && (
        <div className="pointer-events-auto flex max-w-full items-center gap-3 border border-white/15 bg-[#0B0C0E] px-4 py-3 shadow-[0_18px_40px_rgba(0,0,0,0.55)]">
          <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#EDEFF2] text-[#17191C]">
            <Check className="h-3.5 w-3.5" strokeWidth={2.8} />
          </span>
          <p className="min-w-0 truncate text-[11px] text-white/80">
            <span className="font-bold uppercase tracking-[0.06em] text-[#EDEFF2]">{toast.title}</span> a été ajouté au
            panier.
          </p>
          <button
            type="button"
            onClick={openCart}
            className="shrink-0 border-l border-white/15 pl-3 text-[10px] font-bold uppercase tracking-[0.12em] text-white/70 transition-colors hover:text-white"
          >
            Voir
          </button>
        </div>
      )}
    </div>
  );
}
