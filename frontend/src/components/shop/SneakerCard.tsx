import { useState } from 'react';
import type { Product } from '../../types';
import { formatPrice } from '../../utils/format';
import { useCart } from '../../context/CartContext';
import { Heart, ShoppingCart, Check } from 'lucide-react';

interface SneakerCardProps {
  product: Product;
}

export default function SneakerCard({ product }: SneakerCardProps) {
  const { addToCart } = useCart();
  const [selectedSize, setSelectedSize] = useState<string>(
    product.sizes?.[0]?.size || '42'
  );
  const [isLiked, setIsLiked] = useState(false);
  const [justAdded, setJustAdded] = useState(false);

  const primaryImage =
    product.images?.[0]?.url ||
    'https://images.unsplash.com/photo-1552346154-21d32810aba3?w=800';

  const handleAdd = () => {
    addToCart(product, selectedSize);
    setJustAdded(true);
    setTimeout(() => setJustAdded(false), 1500);
  };

  const activePrice = product.price_sale ? product.price_sale : product.price_regular;

  return (
    <div className="group relative rounded-3xl bg-[#141418] border border-[#262630] hover:border-[#D4AF37]/50 p-4 transition-all duration-300 shadow-xl flex flex-col justify-between overflow-hidden">
      {/* Badges Top */}
      <div className="relative w-full h-44 sm:h-48 rounded-2xl bg-[#0A0A0C] overflow-hidden flex items-center justify-center p-3">
        <div className="absolute top-2.5 left-2.5 z-10 flex flex-wrap gap-1.5">
          {product.is_new_drop && (
            <span className="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-[#D4AF37] text-[#0A0A0C]">
              DROP
            </span>
          )}
          {product.price_sale && (
            <span className="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-red-600 text-white">
              PROMO
            </span>
          )}
        </div>

        {/* Heart Wishlist */}
        <button
          type="button"
          onClick={() => setIsLiked(!isLiked)}
          className={`absolute top-2.5 right-2.5 z-10 p-2 rounded-full backdrop-blur-md border transition-all ${
            isLiked
              ? 'bg-red-500/20 text-red-500 border-red-500/40'
              : 'bg-black/40 text-white/70 border-white/10 hover:text-white'
          }`}
        >
          <Heart className="w-4 h-4 fill-current" />
        </button>

        {/* Sneaker Image */}
        <img
          src={primaryImage}
          alt={product.name}
          className="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-500"
        />
      </div>

      {/* Content */}
      <div className="mt-3 flex-1 flex flex-col justify-between">
        <div>
          <span className="text-[10px] font-bold uppercase tracking-widest text-[#D4AF37]">
            {product.brand?.name || 'RCC'}
          </span>
          <h3 className="text-sm font-bold text-white line-clamp-2 leading-snug mt-0.5">
            {product.name}
          </h3>
        </div>

        {/* Size Picker Row */}
        <div className="mt-3">
          <div className="flex items-center justify-between text-[11px] text-[#8E8E9F] font-semibold mb-1">
            <span>Pointure EU :</span>
            <span className="text-[#EDE8DB] font-bold">{selectedSize}</span>
          </div>

          <div className="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-1">
            {product.sizes?.map((s) => (
              <button
                key={s.id}
                type="button"
                onClick={() => setSelectedSize(s.size)}
                className={`px-2.5 py-1 rounded-lg text-xs font-bold transition-all whitespace-nowrap ${
                  selectedSize === s.size
                    ? 'bg-[#EDE8DB] text-[#0A0A0C] shadow-md scale-105'
                    : 'bg-[#1C1C24] text-[#8E8E9F] hover:bg-[#262632] hover:text-white'
                }`}
              >
                {s.size}
              </button>
            ))}
          </div>
        </div>

        {/* Price & Add CTA Button */}
        <div className="mt-4 pt-3 border-t border-[#262630] flex items-center justify-between gap-2">
          <div>
            <div className="text-base font-black text-white">
              {formatPrice(activePrice)}
            </div>
            {product.price_sale && (
              <div className="text-[11px] text-[#8E8E9F] line-through font-medium">
                {formatPrice(product.price_regular)}
              </div>
            )}
          </div>

          <button
            type="button"
            onClick={handleAdd}
            className={`px-3.5 py-2 rounded-xl text-xs font-extrabold flex items-center gap-1.5 transition-all shadow-md ${
              justAdded
                ? 'bg-emerald-500 text-white'
                : 'bg-[#EDE8DB] text-[#0A0A0C] hover:bg-white active:scale-95'
            }`}
          >
            {justAdded ? (
              <>
                <Check className="w-3.5 h-3.5" />
                <span>Ajouté</span>
              </>
            ) : (
              <>
                <ShoppingCart className="w-3.5 h-3.5" />
                <span>Ajouter</span>
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}
