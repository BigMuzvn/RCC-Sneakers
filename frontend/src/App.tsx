import { useEffect, useState } from 'react';
import { CartProvider } from './context/CartContext';
import Navbar from './components/layout/Navbar';
import SneakerCard from './components/shop/SneakerCard';
import CartDrawer from './components/cart/CartDrawer';
import CheckoutModal from './components/checkout/CheckoutModal';
import { fetchProducts } from './services/api';
import type { Product } from './types';
import { Sparkles, ShieldCheck, Truck, RefreshCw } from 'lucide-react';

function ShopShowroom() {
  const [products, setProducts] = useState<Product[]>([]);
  const [isCheckoutOpen, setIsCheckoutOpen] = useState(false);
  const [activeCategory, setActiveCategory] = useState('ALL');

  useEffect(() => {
    fetchProducts().then(setProducts);
  }, []);

  const filteredProducts = products.filter((p) => {
    if (activeCategory === 'DROPS') return p.is_new_drop;
    if (activeCategory === 'MEN') return p.gender === 'MEN' || p.gender === 'UNISEX';
    if (activeCategory === 'WOMEN') return p.gender === 'WOMEN' || p.gender === 'UNISEX';
    return true;
  });

  return (
    <div className="min-h-screen bg-[#0A0A0C] text-white flex flex-col font-sans">
      <Navbar />

      {/* Hero Section */}
      <section className="relative py-12 sm:py-20 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full text-center overflow-hidden">
        <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-[#181820] border border-[#D4AF37]/40 text-[#EDE8DB] text-xs font-bold uppercase tracking-widest mb-4 shadow-lg">
          <Sparkles className="w-4 h-4 text-[#D4AF37]" />
          <span>Nouvelle Collection Exclusive 2026</span>
        </div>

        <h1 className="text-4xl sm:text-6xl font-black uppercase tracking-tight text-white leading-none">
          LE MOUVEMENT COMMENCE <br />
          <span className="text-[#EDE8DB]">AVEC TOI</span>
        </h1>

        <p className="text-[#8E8E9F] text-sm sm:text-base max-w-xl mx-auto mt-4 font-medium leading-relaxed">
          Découvre les paires les plus recherchées au monde. Authenticité 100% garantie, livraison express à Abidjan et expédition dans toute la sous-région.
        </p>

        {/* Category Pills */}
        <div className="flex items-center justify-center gap-2 sm:gap-3 flex-wrap mt-8">
          {[
            { id: 'ALL', label: 'Toutes les paires' },
            { id: 'DROPS', label: '🔥 New Drops' },
            { id: 'MEN', label: 'Hommes' },
            { id: 'WOMEN', label: 'Femmes' },
          ].map((cat) => (
            <button
              key={cat.id}
              onClick={() => setActiveCategory(cat.id)}
              className={`px-5 py-2.5 rounded-full text-xs font-extrabold transition-all uppercase tracking-wider ${
                activeCategory === cat.id
                  ? 'bg-[#EDE8DB] text-[#0A0A0C] shadow-lg scale-105'
                  : 'bg-[#181820] text-[#8E8E9F] hover:bg-[#262632] hover:text-white'
              }`}
            >
              {cat.label}
            </button>
          ))}
        </div>
      </section>

      {/* Catalog Grid */}
      <main className="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-20 w-full">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
          {filteredProducts.map((product) => (
            <SneakerCard key={product.id} product={product} />
          ))}
        </div>
      </main>

      {/* Reassurance Footer Perks */}
      <footer className="border-t border-[#262630] bg-[#121216] py-12 px-4 sm:px-6 lg:px-8">
        <div className="max-w-7xl mx-auto grid grid-cols-1 sm:grid-cols-3 gap-6 text-center">
          <div className="p-4 rounded-2xl bg-[#0A0A0C] border border-[#262630] flex flex-col items-center">
            <Truck className="w-8 h-8 text-[#D4AF37] mb-2" />
            <h4 className="text-sm font-bold text-white uppercase">Livraison Rapide</h4>
            <p className="text-xs text-[#8E8E9F] mt-1">Expédition sous 24h à Abidjan & villes de Côte d'Ivoire</p>
          </div>

          <div className="p-4 rounded-2xl bg-[#0A0A0C] border border-[#262630] flex flex-col items-center">
            <ShieldCheck className="w-8 h-8 text-[#D4AF37] mb-2" />
            <h4 className="text-sm font-bold text-white uppercase">100% Authentique</h4>
            <p className="text-xs text-[#8E8E9F] mt-1">Chaque paire est inspectée et certifiée avant envoi</p>
          </div>

          <div className="p-4 rounded-2xl bg-[#0A0A0C] border border-[#262630] flex flex-col items-center">
            <RefreshCw className="w-8 h-8 text-[#D4AF37] mb-2" />
            <h4 className="text-sm font-bold text-white uppercase">Essayage & Échanges</h4>
            <p className="text-xs text-[#8E8E9F] mt-1">Échange rapide de pointure sous 48h si besoin</p>
          </div>
        </div>

        <div className="text-center text-xs text-[#8E8E9F] mt-8 pt-6 border-t border-[#262630]">
          © 2026 RCC SNEAKERS • React + PHP REST API + MySQL
        </div>
      </footer>

      {/* Drawer & Modal */}
      <CartDrawer onOpenCheckout={() => setIsCheckoutOpen(true)} />
      <CheckoutModal isOpen={isCheckoutOpen} onClose={() => setIsCheckoutOpen(false)} />
    </div>
  );
}

export default function App() {
  return (
    <CartProvider>
      <ShopShowroom />
    </CartProvider>
  );
}
