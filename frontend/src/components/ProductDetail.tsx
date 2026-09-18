import { useEffect, useMemo, useState } from 'react';
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';
import { useCart } from '../context/cart-context';
import Navbar from './Navbar';
import Footer from './Footer';
import ProductCard from './ProductCard';
import { CATEGORIES, type Product } from '../api/catalogue';
import { useCatalogue } from '../context/catalogue-context';
import { formatXof } from '../utils/format';

export default function ProductDetail() {
  const { slug } = useParams();
  const { products, loading } = useCatalogue();
  const product = products.find((item) => item.slug === slug);

  // Tant que le catalogue n'est pas arrivé, conclure « ce modèle n'existe
  // pas » serait faux : on renverrait à la boutique quelqu'un qui a suivi un
  // lien parfaitement valide.
  if (loading) {
    return (
      <div className="flex min-h-[100svh] w-full items-center justify-center bg-[#0A0B0C]">
        <p className="text-[11px] uppercase tracking-[0.2em] text-white/40">Chargement…</p>
      </div>
    );
  }

  if (!product) return <Navigate to="/boutique" replace />;

  // remounts on navigation between products, which resets the picked size
  return <ProductView key={product.slug} product={product} />;
}

function ProductView({ product }: { product: Product }) {
  const { products } = useCatalogue();
  const [size, setSize] = useState<string | null>(null);
  const { add } = useCart();
  const navigate = useNavigate();

  useEffect(() => {
    window.scrollTo(0, 0);
  }, []);

  const addToCart = () => {
    if (size === null) return;
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
    });
  };

  const related = useMemo(
    () => products.filter((item) => item.id !== product.id && item.category === product.category).slice(0, 4),
    [products, product],
  );

  const selectedVariant = product.variants.find((variant) => variant.size === size);
  const discount = product.old_price_xof
    ? Math.round((1 - product.price_xof / product.old_price_xof) * 100)
    : null;
  const categoryLabel = CATEGORIES.find((item) => item.id === product.category)?.label ?? product.category;

  return (
    <div className="flex min-h-[100svh] w-full flex-col bg-[#0A0B0C]">
      <div
        aria-hidden="true"
        className="fixed inset-0 -z-10"
        style={{
          background: `radial-gradient(ellipse 95% 55% at 50% 0%, ${product.accent}2E 0%, #23272C 30%, #14171A 60%, #0A0B0C 100%)`,
        }}
      />

      <div className="flex-1 px-4 py-4 sm:px-8 sm:py-6 lg:px-14 lg:py-8">
        <Navbar />

        {/* ---------- BREADCRUMB ---------- */}
        <nav aria-label="Fil d'Ariane" className="mt-8 flex items-center gap-1.5 text-[10px] uppercase tracking-[0.16em] text-white/40 sm:mt-10">
          <Link to="/" className="transition-colors hover:text-white">
            Accueil
          </Link>
          <ChevronRight className="h-3 w-3" strokeWidth={2} />
          <Link to="/boutique" className="transition-colors hover:text-white">
            Boutique
          </Link>
          <ChevronRight className="h-3 w-3" strokeWidth={2} />
          <span className="truncate text-white/70">{product.model}</span>
        </nav>

        {/* ---------- PRODUCT ---------- */}
        <div className="mt-6 grid grid-cols-1 items-start gap-8 lg:grid-cols-[1.15fr_1fr] lg:gap-14">
          {/* visual */}
          <div className="relative flex aspect-[4/3] items-center justify-center overflow-hidden border border-white/10 bg-white/[0.02]">
            <div
              aria-hidden="true"
              className="absolute inset-0"
              style={{
                background: `radial-gradient(ellipse 78% 68% at 50% 56%, ${product.accent}8C 0%, ${product.accent}45 38%, ${product.accent}1A 60%, transparent 78%)`,
              }}
            />
            <span
              aria-hidden="true"
              className="pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 select-none whitespace-nowrap font-display uppercase leading-none tracking-[-0.02em] text-white/[0.09] text-[clamp(36px,9vw,120px)]"
            >
              {product.brand}
            </span>

            {product.image ? (
              <img
                src={product.image}
                alt={`${product.brand} ${product.model} — ${product.colorway}`}
                className="relative h-full w-full scale-105 object-contain p-4 drop-shadow-[0_30px_45px_rgba(0,0,0,0.55)]"
              />
            ) : (
              <span className="relative text-[10px] uppercase tracking-[0.24em] text-white/40">Visuel à venir</span>
            )}

            {product.is_new_drop && (
              <span className="absolute left-4 top-4 rounded-full bg-[#EDEFF2] px-3 py-1 text-[10px] font-bold uppercase tracking-[0.1em] text-[#17191C]">
                Nouveau
              </span>
            )}
          </div>

          {/* info */}
          <div>
            <p className="text-[11px] uppercase tracking-[0.24em] text-white/50">{product.brand}</p>
            <h1 className="mt-2 font-display uppercase leading-[1.05] tracking-[-0.01em] text-[#EDEFF2] text-[clamp(26px,4.5vw,42px)]">
              {product.model}
            </h1>
            <p className="mt-2 text-xs text-white/55 sm:text-sm">{product.colorway}</p>

            <div className="mt-5 flex flex-wrap items-baseline gap-3">
              <span className="font-display text-[28px] text-[#EDEFF2] sm:text-[34px]">
                {formatXof(product.price_xof)}
              </span>
              {product.old_price_xof && (
                <span className="text-sm text-white/35 line-through">{formatXof(product.old_price_xof)}</span>
              )}
              {discount !== null && (
                <span className="rounded-full bg-[#C8242F] px-2.5 py-[3px] text-[10px] font-bold uppercase tracking-[0.1em] text-white">
                  -{discount}%
                </span>
              )}
            </div>

            {/* sizes */}
            <div className="mt-8">
              <div className="flex items-baseline justify-between gap-3">
                <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-[#EDEFF2]">Taille (EU)</p>
                {selectedVariant && selectedVariant.stock <= 2 && (
                  <p className="text-[10px] uppercase tracking-[0.12em] text-[#E8894A]">
                    Plus que {selectedVariant.stock} {selectedVariant.stock > 1 ? 'paires' : 'paire'}
                  </p>
                )}
              </div>

              <div className="mt-3 grid grid-cols-4 gap-2 sm:grid-cols-7">
                {product.variants.map((variant) => {
                  const available = variant.stock > 0;
                  const selected = size === variant.size;
                  return (
                    <button
                      key={variant.size}
                      type="button"
                      disabled={!available}
                      aria-pressed={selected}
                      onClick={() => setSize(variant.size)}
                      className={`border py-2.5 text-[12px] font-bold transition-colors ${
                        selected
                          ? 'border-[#EDEFF2] bg-[#EDEFF2] text-[#17191C]'
                          : available
                            ? 'border-white/15 text-white/80 hover:border-white/50 hover:text-white'
                            : 'cursor-not-allowed border-white/5 text-white/20 line-through'
                      }`}
                    >
                      {variant.size}
                    </button>
                  );
                })}
              </div>
            </div>

            {/* actions */}
            <div className="mt-6 flex items-center gap-3 sm:gap-4">
              <button
                type="button"
                disabled={!size}
                onClick={addToCart}
                className="flex-1 bg-white px-4 py-3.5 text-[11px] font-bold uppercase tracking-[0.1em] text-[#141516] transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-30 sm:tracking-[0.14em]"
              >
                Ajouter au panier
              </button>
              <button
                type="button"
                disabled={!size}
                onClick={() => {
                  addToCart();
                  navigate('/checkout');
                }}
                className="flex-1 border border-white px-4 py-3.5 text-[11px] font-bold uppercase tracking-[0.1em] text-white transition-colors hover:bg-white hover:text-[#141516] disabled:cursor-not-allowed disabled:opacity-30 sm:tracking-[0.14em]"
              >
                Acheter
              </button>
            </div>
            {!size && (
              <p className="mt-2.5 text-[10px] uppercase tracking-[0.12em] text-white/40">
                Sélectionnez une taille pour continuer
              </p>
            )}

            {/* description */}
            <h2 className="mt-9 text-[11px] font-bold uppercase tracking-[0.18em] text-[#EDEFF2]">Inspiration</h2>
            <p className="mt-2 text-[11px] leading-[1.75] text-white/60 sm:text-xs">{product.description}</p>

            {/* meta */}
            <dl className="mt-8 grid grid-cols-2 gap-x-6 gap-y-4 border-t border-white/10 pt-6 sm:grid-cols-3">
              <div>
                <dt className="text-[9px] uppercase tracking-[0.18em] text-white/40">Référence</dt>
                <dd className="mt-1 text-[11px] text-white/75">{product.sku ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-[9px] uppercase tracking-[0.18em] text-white/40">Catégorie</dt>
                <dd className="mt-1 text-[11px] text-white/75">{categoryLabel}</dd>
              </div>
              <div>
                <dt className="text-[9px] uppercase tracking-[0.18em] text-white/40">Genre</dt>
                <dd className="mt-1 text-[11px] capitalize text-white/75">{product.gender}</dd>
              </div>
            </dl>
          </div>
        </div>

        {/* ---------- RELATED ---------- */}
        {related.length > 0 && (
          <section className="mt-16 pb-6 sm:mt-20">
            <h2 className="text-[11px] font-bold uppercase tracking-[0.2em] text-[#EDEFF2]">Vous aimerez aussi</h2>
            <div className="mt-4 grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
              {related.map((item) => (
                <ProductCard key={item.id} product={item} />
              ))}
            </div>
          </section>
        )}
      </div>

      <Footer />
    </div>
  );
}
