import { useState } from 'react';
import { Check, Plus, X } from 'lucide-react';

type Size = { label: string; stock: number };

/**
 * Quick-add affordance on a product card: opens a size grid over the visual so a pair
 * can be added without opening the detail page. Sits above the card's stretched link.
 */
export default function QuickAdd({
  sizes,
  onAdd,
  label,
}: {
  sizes: Size[];
  onAdd: (size: string) => void;
  label: string;
}) {
  const [open, setOpen] = useState(false);
  const [added, setAdded] = useState<string | null>(null);
  const available = sizes.filter((size) => size.stock > 0);

  if (available.length === 0) return null;

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen((value) => !value)}
        aria-label={open ? 'Fermer le choix de taille' : `Ajouter ${label} au panier`}
        aria-expanded={open}
        className="absolute bottom-3 right-3 z-30 flex h-9 w-9 items-center justify-center rounded-full bg-[#EDEFF2] text-[#17191C] opacity-0 transition-opacity duration-200 hover:opacity-100 focus-visible:opacity-100 group-hover:opacity-100 max-lg:opacity-100 motion-reduce:transition-none"
      >
        {open ? <X className="h-4 w-4" strokeWidth={2.4} /> : <Plus className="h-4 w-4" strokeWidth={2.6} />}
      </button>

      {/* /94 is not on Tailwind's opacity scale — that class was dropped and the panel had no
          background at all, which is why the sizes were unreadable over the product shot */}
      <div
        className={`absolute inset-0 z-20 flex flex-col justify-center bg-[#08090B]/90 px-3 backdrop-blur-md transition-opacity duration-200 motion-reduce:transition-none ${
          open ? 'opacity-100' : 'pointer-events-none opacity-0'
        }`}
      >
        {added ? (
          <div className="flex flex-col items-center gap-2 text-center">
            <span className="flex h-9 w-9 items-center justify-center rounded-full bg-[#EDEFF2] text-[#17191C]">
              <Check className="h-4 w-4" strokeWidth={2.6} />
            </span>
            <p className="text-[10px] uppercase tracking-[0.14em] text-[#EDEFF2]">Taille {added} ajoutée</p>
          </div>
        ) : (
          <>
            <p className="mb-2.5 text-center text-[9px] font-bold uppercase tracking-[0.18em] text-white/60">
              Choisir la taille
            </p>
            <div className="flex flex-wrap justify-center gap-1.5">
              {available.map((size) => (
                <button
                  key={size.label}
                  type="button"
                  tabIndex={open ? 0 : -1}
                  onClick={() => {
                    onAdd(size.label);
                    setAdded(size.label);
                    window.setTimeout(() => {
                      setAdded(null);
                      setOpen(false);
                    }, 1100);
                  }}
                  className="border border-white/35 px-3 py-2 text-[11px] font-bold text-white transition-colors hover:border-[#EDEFF2] hover:bg-[#EDEFF2] hover:text-[#17191C]"
                >
                  {size.label}
                </button>
              ))}
            </div>
          </>
        )}
      </div>
    </>
  );
}
