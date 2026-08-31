import Logo from '../ui/Logo';
import { ShoppingBag, Search, User, Sparkles } from 'lucide-react';
import { useCart } from '../../context/CartContext';

export default function Navbar() {
  const { totalItemsCount, setIsCartOpen } = useCart();

  return (
    <header className="sticky top-0 z-40 w-full bg-[#0A0A0C]/90 backdrop-blur-xl border-b border-[#262630]">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        {/* Logo */}
        <Logo size="md" />

        {/* Reassurance Banner Badge */}
        <div className="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-full bg-[#181820] border border-[#D4AF37]/30 text-xs font-semibold text-[#EDE8DB]">
          <Sparkles className="w-3.5 h-3.5 text-[#D4AF37]" />
          <span>Livraison Express Abidjan & Sous-Région • 100% Authentique</span>
        </div>

        {/* Right Actions */}
        <div className="flex items-center gap-4">
          <button
            type="button"
            className="p-2.5 rounded-full text-[#8E8E9F] hover:text-white hover:bg-[#181820] transition-colors"
            title="Recherche"
          >
            <Search className="w-5 h-5" />
          </button>

          <button
            type="button"
            className="hidden sm:flex p-2.5 rounded-full text-[#8E8E9F] hover:text-white hover:bg-[#181820] transition-colors"
            title="Espace Client"
          >
            <User className="w-5 h-5" />
          </button>

          {/* Cart Trigger */}
          <button
            type="button"
            onClick={() => setIsCartOpen(true)}
            className="relative flex items-center gap-2.5 px-4 py-2 rounded-xl bg-[#EDE8DB] text-[#0A0A0C] font-bold text-xs hover:bg-white transition-all shadow-lg group"
          >
            <ShoppingBag className="w-4 h-4 text-[#0A0A0C] group-hover:scale-110 transition-transform" />
            <span className="hidden sm:inline">Mon Panier</span>
            {totalItemsCount > 0 && (
              <span className="px-2 py-0.5 rounded-full bg-[#0A0A0C] text-[#EDE8DB] text-[11px] font-extrabold">
                {totalItemsCount}
              </span>
            )}
          </button>
        </div>
      </div>
    </header>
  );
}
