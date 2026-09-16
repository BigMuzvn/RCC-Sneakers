import { Link } from 'react-router-dom';
import type { Product } from '../data/products';
import { formatXof } from '../utils/format';
import { useCart } from '../context/cart-context';
import QuickAdd from './QuickAdd';
import FavoriteButton from './FavoriteButton';

export default function ProductCard({ product }: { product: Product }) {
  const { add } = useCart();
  const inStock = product.variants.filter((variant) => variant.stock > 0).length;
  const discount = product.old_price_xof
    ? Math.round((1 - product.price_xof / product.old_price_xof) * 100)
    : null;

  return (
    <article className="group relative flex flex-col overflow-hidden border border-white/10 bg-white/[0.02] transition-colors duration-300 hover:border-white/25 hover:bg-white/[0.04]">
      {/* stretched link: keeps the whole card clickable without nesting buttons inside an anchor */}
      <Link
        to={`/boutique/${product.slug}`}
        className="absolute inset-0 z-10"
        aria-label={`${product.brand} ${product.model} — ${product.colorway}`}
      />

      <FavoriteButton type="sneaker" id={product.id} label={`${product.brand} ${product.model}`} />

      <div className="relative aspect-[4/3] overflow-hidden">
        <div
          aria-hidden="true"
          className="absolute inset-0 opacity-90 transition-opacity duration-500 group-hover:opacity-100 motion-reduce:transition-none"
          style={{
            background: `radial-gradient(ellipse 80% 70% at 50% 56%, ${product.accent}8C 0%, ${product.accent}45 38%, ${product.accent}1A 60%, transparent 78%)`,
          }}
        />

        {product.image ? (
          <img
            src={product.image}
            alt={`${product.brand} ${product.model} — ${product.colorway}`}
            loading="lazy"
            className="absolute inset-0 h-full w-full scale-[1.06] object-contain p-2 drop-shadow-[0_18px_24px_rgba(0,0,0,0.5)] transition-transform duration-500 ease-out group-hover:scale-[1.14] motion-reduce:transition-none"
          />
        ) : (
          <div className="absolute inset-0 flex flex-col items-center justify-center gap-3">
            <span
              aria-hidden="true"
              className="h-16 w-4/5 rounded-[50%] blur-2xl"
              style={{ background: product.accent, opacity: 0.55 }}
            />
            <span className="text-[9px] uppercase tracking-[0.24em] text-white/35">Visuel à venir</span>
          </div>
        )}

        {/* Les pastilles s'empilent à gauche : le coin droit est réservé au
            cœur, seul élément interactif et le seul présent sur toutes les
            cartes. Avant, « Nouveau » et la remise se chevauchaient déjà entre
            eux, tous deux calés en haut à droite. */}
        <div className="absolute left-3 top-3 z-20 flex flex-col items-start gap-1.5">
          {product.is_new_drop && (
            <span className="rounded-full bg-[#EDEFF2] px-2.5 py-[3px] text-[9px] font-bold uppercase tracking-[0.1em] text-[#17191C]">
              Nouveau
            </span>
          )}
          {discount !== null && (
            <span className="rounded-full bg-[#C8242F] px-2.5 py-[3px] text-[9px] font-bold uppercase tracking-[0.1em] text-white">
              -{discount}%
            </span>
          )}
        </div>

        <QuickAdd
          label={`${product.brand} ${product.model}`}
          sizes={product.variants.map((variant) => ({ label: String(variant.size), stock: variant.stock }))}
          onAdd={(size) =>
            add({
              type: 'sneaker',
              id: product.id,
              href: `/boutique/${product.slug}`,
              title: `${product.brand} ${product.model}`,
              subtitle: product.colorway,
              size,
              unit_price_xof: product.price_xof,
              image: product.image,
              accent: product.accent,
            })
          }
        />
      </div>

      <div className="flex flex-1 flex-col p-3 sm:p-4">
        <p className="text-[9px] uppercase tracking-[0.2em] text-white/45 sm:text-[10px]">{product.brand}</p>
        <h3 className="mt-1 text-[12px] font-bold uppercase leading-snug tracking-[0.06em] text-[#EDEFF2] sm:text-[13px]">
          {product.model}
        </h3>
        <p className="mt-1 text-[10px] text-white/50 sm:text-[11px]">{product.colorway}</p>

        <div className="mt-3 flex flex-col gap-0.5 sm:mt-4 sm:flex-row sm:items-end sm:justify-between sm:gap-2">
          <div className="flex flex-col">
            {product.old_price_xof && (
              <span className="text-[10px] text-white/35 line-through sm:text-[11px]">
                {formatXof(product.old_price_xof)}
              </span>
            )}
            <span className="font-display text-[15px] text-[#EDEFF2] sm:text-base">{formatXof(product.price_xof)}</span>
          </div>
          <span className="whitespace-nowrap text-[9px] uppercase tracking-[0.12em] text-white/40 sm:text-[10px]">
            {inStock > 0 ? `${inStock} tailles` : 'épuisé'}
          </span>
        </div>
      </div>
    </article>
  );
}
