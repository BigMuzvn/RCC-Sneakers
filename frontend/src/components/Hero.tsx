import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import Navbar from './Navbar';
import { useCart } from '../context/cart-context';
import { PRODUCTS, type Product } from '../data/products';
import { formatXof } from '../utils/format';

const SLIDE_INTERVAL = 5000;

/**
 * Les quatre paires mises en avant.
 *
 * Ce ne sont pas des visuels de décor : chaque slide **est** un produit du
 * catalogue, désigné par son slug. Le prix, le coloris, le texte et le stock
 * viennent donc de la même source que la boutique, et un achat depuis l'accueil
 * ajoute exactement l'article qu'on voit.
 *
 * Cette liste passera en base à la partie administration — c'est la raison pour
 * laquelle elle ne contient que des slugs et rien de recopié.
 */
const HERO_SLUGS = [
  'nike-shox-tl-black-racer-blue',
  'nike-p-6000-metallic-silver',
  'nike-air-max-95-neon',
  'nike-air-max-plus-sunset',
];

/**
 * Fonds peints à la main pour ces quatre paires : ils ne se déduisent pas d'une
 * seule couleur d'accent, ils ont été calés sur l'image. Tout coloris arrivant
 * plus tard au catalogue reçoit un fond dérivé de son accent — moins ajusté,
 * mais jamais absent.
 */
const BACKGROUNDS: Record<string, string> = {
  'nike-shox-tl-black-racer-blue':
    'radial-gradient(ellipse 78% 72% at 50% 46%, #23409B 0%, #16255C 34%, #0B1026 70%, #05070E 100%)',
  'nike-p-6000-metallic-silver':
    'radial-gradient(ellipse 78% 72% at 50% 46%, #4C525A 0%, #2D3238 36%, #17191C 72%, #0A0B0C 100%)',
  'nike-air-max-95-neon':
    'radial-gradient(ellipse 78% 72% at 50% 46%, #5F6E22 0%, #343A1A 34%, #16180F 70%, #08090A 100%)',
  'nike-air-max-plus-sunset':
    'radial-gradient(ellipse 78% 72% at 50% 46%, #B0550A 0%, #5E2A06 34%, #1F1006 70%, #0A0705 100%)',
};

/** Assombrit une couleur vers le noir — `ratio` 0 = inchangée, 1 = noire. */
const darken = (hex: string, ratio: number) => {
  const value = parseInt(hex.slice(1), 16);
  const channel = (shift: number) => Math.round(((value >> shift) & 255) * (1 - ratio));

  return `rgb(${channel(16)}, ${channel(8)}, ${channel(0)})`;
};

const backgroundFor = (product: Product) =>
  BACKGROUNDS[product.slug] ??
  `radial-gradient(ellipse 78% 72% at 50% 46%, ${product.accent} 0%, ${darken(product.accent, 0.45)} 34%, ${darken(
    product.accent,
    0.78,
  )} 70%, #07080A 100%)`;

const swatchFor = (product: Product) =>
  `linear-gradient(135deg, ${product.accent} 0%, ${darken(product.accent, 0.55)} 100%)`;

/** Familles de coloris : toutes les fiches du catalogue partageant marque et modèle. */
const colorwaysOf = (product: Product) =>
  PRODUCTS.filter((item) => item.brand === product.brand && item.model === product.model);

const SLIDES = HERO_SLUGS.flatMap((slug) => {
  const product = PRODUCTS.find((item) => item.slug === slug);

  if (!product) {
    // Bruyant plutôt que silencieux : une paire renommée au catalogue ferait
    // sinon disparaître un slide sans que personne s'en aperçoive.
    console.warn(`[accueil] produit introuvable au catalogue : ${slug}`);

    return [];
  }

  return [product];
});

export default function Hero() {
  const navigate = useNavigate();
  const { add } = useCart();

  const [active, setActive] = useState(0);
  /** Coloris retenu pour chaque slide, quand le modèle en compte plusieurs. */
  const [colorway, setColorway] = useState<Record<number, number>>({});
  /** Action en attente d'une taille — le panneau de tailles est ouvert. */
  const [pending, setPending] = useState<null | 'cart' | 'buy'>(null);

  const famille = useMemo(() => colorwaysOf(SLIDES[active]), [active]);
  const produit = famille[colorway[active] ?? 0] ?? SLIDES[active];

  // Redémarre à chaque changement, pour qu'un choix manuel dispose d'un
  // intervalle complet. Suspendu pendant le choix d'une taille : voir un slide
  // défiler sous son doigt au moment d'acheter est la meilleure façon de faire
  // ajouter au panier autre chose que ce qu'on visait.
  useEffect(() => {
    if (pending !== null) return;

    const id = setInterval(() => setActive((current) => (current + 1) % SLIDES.length), SLIDE_INTERVAL);

    return () => clearInterval(id);
  }, [active, pending]);

  // Changer de slide referme le panneau : il appartenait à la paire précédente.
  useEffect(() => setPending(null), [active]);

  const disponibles = produit.variants.filter((variant) => variant.stock > 0);
  const enRupture = disponibles.length === 0;

  const choisirTaille = (size: number) => {
    add({
      type: 'sneaker',
      id: produit.id,
      href: `/boutique/${produit.slug}`,
      title: `${produit.brand} ${produit.model}`,
      subtitle: produit.colorway,
      size: String(size),
      unit_price_xof: produit.price_xof,
      image: produit.image,
      accent: produit.accent,
    });

    const suite = pending;
    setPending(null);

    if (suite === 'buy') navigate('/checkout');
  };

  return (
    <section className="relative isolate flex h-[100svh] w-full flex-col overflow-hidden bg-[#05070E] px-4 py-4 sm:px-8 sm:py-6 lg:px-14 lg:py-8">
      {/* fonds par slide, en fondu croisé */}
      {SLIDES.map((item, index) => (
        <div
          key={item.slug}
          aria-hidden="true"
          className={`absolute inset-0 -z-10 transition-opacity duration-700 ease-out motion-reduce:transition-none ${
            index === active ? 'opacity-100' : 'opacity-0'
          }`}
          style={{ background: backgroundFor(index === active ? produit : item) }}
        />
      ))}

      {/* filets décoratifs */}
      <span className="pointer-events-none absolute -top-16 right-[26%] h-[300px] w-px rotate-[22deg] bg-gradient-to-b from-white/20 to-transparent sm:h-[440px]" />
      <span className="pointer-events-none absolute -right-20 -top-24 h-[220px] w-[220px] rounded-full border border-white/10 sm:-right-28 sm:-top-32 sm:h-[380px] sm:w-[380px]" />

      <Navbar />

      {/* ---------- SCÈNE ---------- */}
      <div className="relative mt-4 min-h-0 flex-1 sm:mt-6">
        <div className="pointer-events-none absolute left-1/2 top-[48%] z-0 -translate-x-1/2 -translate-y-1/2 select-none">
          <div className="relative">
            <span
              aria-hidden="true"
              className="absolute bottom-full left-0 mb-[-0.45em] font-display uppercase leading-none tracking-[0.3em] text-white/25 text-[clamp(14px,3.6vw,50px)]"
            >
              RCC
            </span>
            <h1 className="whitespace-nowrap font-display uppercase leading-none tracking-[-0.02em] text-white/[0.085] text-[clamp(54px,15.5vw,205px)]">
              Sneakers
            </h1>
          </div>
        </div>

        {SLIDES.map((item, index) => {
          const affiche = index === active ? produit : item;

          return affiche.image ? (
            <img
              key={item.slug}
              src={affiche.image}
              alt={index === active ? `${affiche.brand} ${affiche.model} — ${affiche.colorway}` : ''}
              aria-hidden={index !== active}
              className={`absolute left-1/2 top-[52%] z-10 h-[118%] w-auto max-w-full -translate-x-1/2 -translate-y-1/2 object-contain drop-shadow-[0_35px_50px_rgba(0,0,0,0.55)] transition-[opacity,transform] duration-700 ease-out motion-reduce:transition-none sm:max-w-[96%] ${
                index === active ? 'scale-100 opacity-100' : 'pointer-events-none scale-95 opacity-0'
              }`}
            />
          ) : null;
        })}
      </div>

      {/* ---------- PASTILLES DE SLIDE ---------- */}
      <div
        className="relative z-20 mt-2 flex shrink-0 items-center justify-center gap-2 sm:absolute sm:right-4 sm:top-1/2 sm:mt-0 sm:-translate-y-1/2 sm:flex-col sm:gap-3 lg:right-7"
        role="tablist"
        aria-label="Sélection du modèle"
      >
        {SLIDES.map((item, index) => (
          <button
            key={item.slug}
            type="button"
            role="tab"
            aria-selected={index === active}
            aria-label={`${item.brand} ${item.model}`}
            title={`${item.brand} ${item.model}`}
            onClick={() => setActive(index)}
            className="flex h-6 w-6 items-center justify-center sm:h-5 sm:w-5"
          >
            <span
              className={`rounded-full transition-all duration-300 ease-out motion-reduce:transition-none ${
                index === active ? 'h-[9px] w-[9px] bg-[#EDEFF2]' : 'h-[7px] w-[7px] bg-white/25 hover:bg-white/60'
              }`}
            />
          </button>
        ))}
      </div>

      {/* ---------- BARRE BASSE ---------- */}
      <div className="relative z-10 mt-2 grid shrink-0 grid-cols-1 items-end gap-4 sm:mt-0 sm:grid-cols-2 sm:gap-5 lg:grid-cols-[auto_1fr_auto] lg:gap-10">
        {/* ---- coloris ---- */}
        <div key={`${produit.slug}-colors`} className="order-2 animate-slide-in motion-reduce:animate-none lg:order-1">
          {/* Un seul coloris au catalogue par modèle aujourd'hui : on annonce
              celui de la paire plutôt que de simuler un choix qui n'existe pas.
              Dès qu'un second entre au catalogue, la rangée devient un vrai
              sélecteur et change le produit ajouté au panier. */}
          <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-white sm:text-[11px]">
            {famille.length > 1 ? 'Choisir la couleur :' : `Coloris : ${produit.colorway}`}
          </p>
          <div className="mt-2.5 flex items-center gap-3 sm:mt-3 sm:gap-4">
            {famille.map((item, index) => {
              const choisi = item.id === produit.id;

              return (
                <button
                  key={item.id}
                  type="button"
                  aria-label={item.colorway}
                  aria-pressed={choisi}
                  title={item.colorway}
                  disabled={famille.length === 1}
                  onClick={() => setColorway((current) => ({ ...current, [active]: index }))}
                  className={`h-11 w-11 rounded-full border transition-transform sm:h-[46px] sm:w-[46px] ${
                    famille.length > 1 ? 'hover:scale-105' : 'cursor-default'
                  } ${choisi ? 'border-[#EDEFF2]' : 'border-white/20'}`}
                  style={{ background: swatchFor(item) }}
                />
              );
            })}
          </div>
        </div>

        {/* ---- actions ---- */}
        <div className="relative order-3 flex items-center gap-3 sm:gap-4 lg:order-2 lg:justify-center">
          {/* Panneau de tailles : il s'ouvre au-dessus des boutons plutôt que de
              s'insérer dans la barre. L'accueil tient en un écran sans
              défilement — une rangée de plus le ferait déborder sur les
              hauteurs contraintes. */}
          {pending !== null && (
            <div className="absolute bottom-full left-0 right-0 z-30 mb-3 border border-white/15 bg-[#08090B]/95 p-3 backdrop-blur-md sm:left-auto sm:right-auto sm:w-[320px]">
              <div className="flex items-baseline justify-between">
                <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-[#EDEFF2]">Choisir la taille</p>
                <button
                  type="button"
                  onClick={() => setPending(null)}
                  className="text-[10px] uppercase tracking-[0.12em] text-white/45 transition-colors hover:text-white"
                >
                  Fermer
                </button>
              </div>

              <div className="mt-2.5 grid grid-cols-4 gap-1.5 sm:grid-cols-7">
                {produit.variants.map((variant) => (
                  <button
                    key={variant.size}
                    type="button"
                    disabled={variant.stock === 0}
                    onClick={() => choisirTaille(variant.size)}
                    className={`py-2 text-[11px] font-bold transition-colors ${
                      variant.stock > 0
                        ? 'border border-white/20 text-white/85 hover:border-white hover:bg-white hover:text-[#141516]'
                        : 'cursor-not-allowed border border-white/5 text-white/20 line-through'
                    }`}
                  >
                    {variant.size}
                  </button>
                ))}
              </div>
            </div>
          )}

          <button
            type="button"
            disabled={enRupture}
            onClick={() => setPending('cart')}
            className="flex-1 bg-white px-3 py-3.5 text-[10px] font-bold uppercase tracking-[0.08em] text-[#141516] transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40 sm:flex-none sm:px-9 sm:py-3 sm:text-[11px] sm:tracking-[0.14em]"
          >
            {enRupture ? 'Épuisé' : 'Ajouter au panier'}
          </button>
          <button
            type="button"
            disabled={enRupture}
            onClick={() => setPending('buy')}
            className="flex-1 border border-white px-3 py-3.5 text-[10px] font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-white hover:text-[#141516] disabled:cursor-not-allowed disabled:opacity-40 sm:flex-none sm:px-9 sm:py-3 sm:text-[11px] sm:tracking-[0.14em]"
          >
            Acheter
          </button>
        </div>

        {/* ---- détail ---- */}
        <div
          key={`${produit.slug}-details`}
          className="order-1 w-full animate-slide-in motion-reduce:animate-none sm:col-span-2 lg:order-3 lg:col-span-1 lg:max-w-[330px] lg:justify-self-end"
        >
          <div className="flex flex-col items-start gap-1.5">
            <span className="rounded-full bg-[#C5C9CE] px-3 py-[3px] text-[10px] lowercase tracking-wide text-[#22252A] sm:self-end">
              {produit.is_new_drop ? 'nouveauté' : 'exclusif'}
            </span>
            <div className="flex items-baseline gap-3">
              <span className="font-display text-[26px] text-[#EDEFF2] sm:text-[32px]">
                {formatXof(produit.price_xof)}
              </span>
              {/* Le nom mène à la fiche : la paire de l'accueil et celle de la
                  boutique sont le même article, le parcours doit le montrer. */}
              <Link
                to={`/boutique/${produit.slug}`}
                className="text-[13px] font-bold uppercase leading-[1.3] tracking-[0.12em] text-[#EDEFF2] underline-offset-4 transition-colors hover:underline sm:text-sm"
              >
                {produit.brand}
                <br />
                {produit.model}
              </Link>
            </div>
          </div>

          <h2 className="mt-3 text-[11px] font-bold uppercase tracking-[0.18em] text-[#EDEFF2] sm:mt-5">Inspiration</h2>
          <p className="mt-1.5 line-clamp-4 text-[11px] leading-[1.6] text-white/60 sm:mt-2 sm:text-xs sm:leading-[1.7]">
            {produit.description}
          </p>
        </div>
      </div>
    </section>
  );
}
