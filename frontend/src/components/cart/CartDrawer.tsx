import { useCart } from '../../context/CartContext';
import { formatPrice } from '../../utils/format';
import { X, Trash2, Plus, Minus, ArrowRight, ShoppingBag, ShieldCheck } from 'lucide-react';

interface CartDrawerProps {
  onOpenCheckout: () => void;
}

export default function CartDrawer({ onOpenCheckout }: CartDrawerProps) {
  const { cart, isCartOpen, setIsCartOpen, removeFromCart, updateQuantity, subtotal } =
    useCart();

  if (!isCartOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-hidden">
      {/* Backdrop */}
      <div
        onClick={() => setIsCartOpen(false)}
        className="absolute inset-0 bg-black/70 backdrop-blur-sm transition-opacity"
      />

      <div className="absolute inset-y-0 right-0 max-w-full flex pl-10">
        <div className="w-screen max-w-md bg-[#121216] border-l border-[#262630] text-white flex flex-col justify-between shadow-2xl">
          {/* Header */}
          <div className="p-5 border-b border-[#262630] flex items-center justify-between">
            <div className="flex items-center gap-2">
              <ShoppingBag className="w-5 h-5 text-[#D4AF37]" />
              <h2 className="text-lg font-extrabold uppercase tracking-wide">
                Mon Panier RCC
              </h2>
            </div>
            <button
              onClick={() => setIsCartOpen(false)}
              className="p-2 rounded-full text-[#8E8E9F] hover:text-white hover:bg-[#181820]"
            >
              <X className="w-5 h-5" />
            </button>
          </div>

          {/* Cart Items List */}
          <div className="flex-1 overflow-y-auto p-5 space-y-4">
            {cart.length === 0 ? (
              <div className="h-full flex flex-col items-center justify-center text-center text-[#8E8E9F] py-12">
                <ShoppingBag className="w-16 h-16 text-[#262630] mb-4" />
                <p className="text-sm font-semibold text-white">Ton panier est vide pour l'instant</p>
                <p className="text-xs mt-1 max-w-xs">
                  Sélectionne ta pointure et ajoute tes paires de sneakers préférées !
                </p>
              </div>
            ) : (
              cart.map((item) => (
                <div
                  key={`${item.productId}-${item.size}`}
                  className="flex gap-4 p-3 rounded-2xl bg-[#181820] border border-[#262630] items-center"
                >
                  <img
                    src={item.image}
                    alt={item.name}
                    className="w-16 h-16 rounded-xl object-cover bg-black"
                  />

                  <div className="flex-1 min-w-0">
                    <div className="text-[10px] font-bold text-[#D4AF37] uppercase">
                      {item.brandName}
                    </div>
                    <h4 className="text-xs font-bold text-white truncate">{item.name}</h4>
                    <div className="text-[11px] text-[#8E8E9F] font-semibold mt-0.5">
                      Pointure: <span className="text-[#EDE8DB]">{item.size}</span>
                    </div>
                    <div className="text-xs font-extrabold text-white mt-1">
                      {formatPrice(item.price)}
                    </div>
                  </div>

                  {/* Quantity Stepper */}
                  <div className="flex flex-col items-end gap-2">
                    <button
                      onClick={() => removeFromCart(item.productId, item.size)}
                      className="text-[#8E8E9F] hover:text-red-400 p-1"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>

                    <div className="flex items-center gap-1.5 bg-[#0A0A0C] border border-[#262630] rounded-lg px-2 py-0.5 text-xs font-bold">
                      <button
                        onClick={() => updateQuantity(item.productId, item.size, -1)}
                        className="hover:text-[#D4AF37]"
                      >
                        <Minus className="w-3 h-3" />
                      </button>
                      <span className="w-4 text-center">{item.quantity}</span>
                      <button
                        onClick={() => updateQuantity(item.productId, item.size, 1)}
                        className="hover:text-[#D4AF37]"
                      >
                        <Plus className="w-3 h-3" />
                      </button>
                    </div>
                  </div>
                </div>
              ))
            )}
          </div>

          {/* Footer & Checkout Trigger */}
          {cart.length > 0 && (
            <div className="p-5 border-t border-[#262630] bg-[#0A0A0C] space-y-4">
              <div className="flex items-center justify-between text-sm">
                <span className="text-[#8E8E9F] font-semibold">Sous-total :</span>
                <span className="text-lg font-black text-white">{formatPrice(subtotal)}</span>
              </div>

              <div className="flex items-center gap-2 text-[11px] text-emerald-400 bg-emerald-950/40 border border-emerald-800/40 p-2.5 rounded-xl">
                <ShieldCheck className="w-4 h-4 shrink-0" />
                <span>Livraison et vérification d'authenticité incluses</span>
              </div>

              <button
                onClick={() => {
                  setIsCartOpen(false);
                  onOpenCheckout();
                }}
                className="w-full py-3.5 rounded-xl bg-[#EDE8DB] text-[#0A0A0C] font-extrabold text-xs uppercase tracking-wider flex items-center justify-center gap-2 hover:bg-white transition-all shadow-xl active:scale-98"
              >
                <span>Passer la commande</span>
                <ArrowRight className="w-4 h-4" />
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
