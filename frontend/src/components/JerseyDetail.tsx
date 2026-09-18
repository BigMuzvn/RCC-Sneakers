import { useEffect, useMemo, useState } from 'react';
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';
import { useCart } from '../context/cart-context';
import Navbar from './Navbar';
import Footer from './Footer';
import JerseyCard from './JerseyCard';
import FavoriteButton from './FavoriteButton';
import type { Jersey } from '../api/catalogue';
import { useCatalogue } from '../context/catalogue-context';
import { formatXof } from '../utils/format';

export default function JerseyDetail() {
  const { slug } = useParams();
  const { jerseys, loading } = useCatalogue();
  const jersey = jerseys.find((item) => item.slug === slug);

  // Voir plus haut, dans la fiche des paires : avant l'arrivée du catalogue,
  // « introuvable » serait un mensonge.
  if (loading) {
    return (
      <div className="flex min-h-[100svh] w-full items-center justify-center bg-[#0A0B0C]">
        <p className="text-[11px] uppercase tracking-[0.2em] text-white/40">Chargement…</p>
      </div>
    );
  }

  if (!jersey) return <Navigate to="/maillots" replace />;

  return <JerseyView key={jersey.id} jersey={jersey} />;
}

function JerseyView({ jersey }: { jersey: Jersey }) {
  const { jerseys } = useCatalogue();
  const [size, setSize] = useState<string | null>(null);
  const { add } = useCart();
  const navigate = useNavigate();

  useEffect(() => {
    window.scrollTo(0, 0);
  }, []);

  const addToCart = () => {
    if (size === null) return;
    add({
      type: 'jersey',
      id: jersey.id,
      href: `/maillots/${jersey.slug}`,
      title: `${jersey.club} — ${jersey.kit}`,
      subtitle: `${jersey.season} · ${jersey.colorway}`,
      size,
      unit_price_xof: jersey.price_xof,
      image: jersey.image,
      accent: jersey.accent,
    });
  };

  // Le même championnat d'abord ; complété par d'autres si le championnat est
  // peu fourni, pour ne jamais afficher une rangée de suggestions à moitié vide.
  const related = useMemo(() => {
    const memeChampionnat = jerseys.filter((i) => i.id !== jersey.id && i.league === jersey.league);
    const autres = jerseys.filter((i) => i.id !== jersey.id && i.league !== jersey.league);

    return [...memeChampionnat, ...autres].slice(0, 4);
  }, [jerseys, jersey]);

  const selectedVariant = jersey.variants.find((variant) => variant.size === size);
  const discount = jersey.old_price_xof
    ? Math.round((1 - jersey.price_xof / jersey.old_price_xof) * 100)
    : null;
  const enRupture = jersey.variants.every((variant) => variant.stock === 0);

  return (
    <div className="flex min-h-[100svh] w-full flex-col bg-[#0A0B0C]">
      <div
        aria-hidden="true"
        className="fixed inset-0 -z-10"
        style={{
          background: `radial-gradient(ellipse 95% 55% at 50% 0%, ${jersey.accent}2E 0%, #23272C 30%, #14171A 60%, #0A0B0C 100%)`,
        }}
      />

      <div className="flex-1 px-4 py-4 sm:px-8 sm:py-6 lg:px-14 lg:py-8">
        <Navbar />

        {/* ---------- FIL D'ARIANE ---------- */}
        <nav
          aria-label="Fil d'Ariane"
          className="mt-8 flex items-center gap-1.5 text-[10px] uppercase tracking-[0.16em] text-white/40 sm:mt-10"
        >
          <Link to="/" className="transition-colors hover:text-white">
            Accueil
          </Link>
          <ChevronRight className="h-3 w-3" strokeWidth={2} />
          <Link to="/maillots" className="transition-colors hover:text-white">
            Maillots
          </Link>
          <ChevronRight className="h-3 w-3" strokeWidth={2} />
          <span className="truncate text-white/70">{jersey.club}</span>
        </nav>

        <div className="mt-6 grid grid-cols-1 items-start gap-8 lg:grid-cols-[1.15fr_1fr] lg:gap-14">
          {/* ---------- VISUEL ---------- */}
          <div className="relative flex aspect-[4/3] items-center justify-center overflow-hidden border border-white/10 bg-white/[0.02]">
            <div
              aria-hidden="true"
              className="absolute inset-0"
              style={{
                background: `radial-gradient(ellipse 78% 68% at 50% 56%, ${jersey.accent}8C 0%, ${jersey.accent}45 38%, ${jersey.accent}1A 60%, transparent 78%)`,
              }}
            />
            {/* Le nom du club en filigrane remplace le visuel manquant : aucun
                maillot n'a encore de rendu, et une zone vide se lirait comme une
                image cassée plutôt que comme un parti pris. */}
            <span
              aria-hidden="true"
              className="pointer-events-none absolute left-1/2 top-[46%] max-w-full -translate-x-1/2 -translate-y-1/2 select-none px-8 text-center font-display uppercase leading-[0.95] tracking-[-0.02em] text-white/[0.09] text-[clamp(24px,5.6vw,78px)]"
            >
              {jersey.club}
            </span>

            {jersey.image ? (
              <img
                src={jersey.image}
                alt={`Maillot ${jersey.club} ${jersey.kit} ${jersey.season}`}
                className="relative h-full w-full scale-105 object-contain p-4 drop-shadow-[0_30px_45px_rgba(0,0,0,0.55)]"
              />
            ) : (
              <span className="absolute bottom-5 left-1/2 -translate-x-1/2 text-[10px] uppercase tracking-[0.24em] text-white/40">Visuel à venir</span>
            )}

            <span className="absolute left-4 top-4 rounded-full border border-white/25 bg-black/35 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.1em] text-white/90 backdrop-blur-sm">
              {jersey.kit}
            </span>

            <FavoriteButton type="jersey" id={jersey.id} label={`le maillot ${jersey.club}`} />
          </div>

          {/* ---------- INFORMATIONS ---------- */}
          <div>
            <p className="text-[11px] uppercase tracking-[0.24em] text-white/50">{jersey.league}</p>
            <h1 className="mt-2 font-display uppercase leading-[1.05] tracking-[-0.01em] text-[#EDEFF2] text-[clamp(26px,4.5vw,42px)]">
              {jersey.club}
            </h1>
            <p className="mt-2 text-xs text-white/55 sm:text-sm">
              {jersey.kit} {jersey.season} — {jersey.colorway}
            </p>

            <div className="mt-5 flex flex-wrap items-baseline gap-3">
              <span className="font-display text-[28px] text-[#EDEFF2] sm:text-[34px]">
                {formatXof(jersey.price_xof)}
              </span>
              {jersey.old_price_xof && (
                <span className="text-sm text-white/35 line-through">{formatXof(jersey.old_price_xof)}</span>
              )}
              {discount !== null && (
                <span className="rounded-full bg-[#C8242F] px-2.5 py-[3px] text-[10px] font-bold uppercase tracking-[0.1em] text-white">
                  -{discount}%
                </span>
              )}
            </div>

            {/* ---------- TAILLES ---------- */}
            <div className="mt-8">
              <div className="flex items-baseline justify-between gap-3">
                <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-[#EDEFF2]">Taille</p>
                {selectedVariant && selectedVariant.stock > 0 && selectedVariant.stock <= 2 && (
                  <p className="text-[10px] uppercase tracking-[0.12em] text-[#E8894A]">
                    Plus que {selectedVariant.stock} {selectedVariant.stock > 1 ? 'pièces' : 'pièce'}
                  </p>
                )}
              </div>

              <div className="mt-3 grid grid-cols-5 gap-2">
                {jersey.variants.map((variant) => {
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

            {/* ---------- ACTIONS ---------- */}
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

            <p className="mt-2.5 text-[10px] uppercase tracking-[0.12em] text-white/40">
              {enRupture
                ? 'Toutes les tailles sont épuisées'
                : !size
                  ? 'Sélectionnez une taille pour continuer'
                  : ' '}
            </p>

            {/* ---------- FLOCAGE ---------- */}
            <h2 className="mt-9 text-[11px] font-bold uppercase tracking-[0.18em] text-[#EDEFF2]">Flocage</h2>
            <p className="mt-2 text-[11px] leading-[1.75] text-white/60 sm:text-xs">
              Nom et numéro possibles sur demande, au style officiel du championnat. Précisez-le nous au moment de
              l'appel de confirmation — le délai de livraison passe alors à 48 h.
            </p>

            <dl className="mt-8 grid grid-cols-2 gap-x-6 gap-y-4 border-t border-white/10 pt-6 sm:grid-cols-3">
              <div>
                <dt className="text-[9px] uppercase tracking-[0.18em] text-white/40">Équipementier</dt>
                <dd className="mt-1 text-[11px] text-white/75">{jersey.brand}</dd>
              </div>
              <div>
                <dt className="text-[9px] uppercase tracking-[0.18em] text-white/40">Saison</dt>
                <dd className="mt-1 text-[11px] text-white/75">{jersey.season}</dd>
              </div>
              <div>
                <dt className="text-[9px] uppercase tracking-[0.18em] text-white/40">Championnat</dt>
                <dd className="mt-1 text-[11px] text-white/75">{jersey.league}</dd>
              </div>
            </dl>
          </div>
        </div>

        {related.length > 0 && (
          <section className="mt-16 pb-6 sm:mt-20">
            <h2 className="text-[11px] font-bold uppercase tracking-[0.2em] text-[#EDEFF2]">Vous aimerez aussi</h2>
            <div className="mt-4 grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
              {related.map((item) => (
                <JerseyCard key={item.id} jersey={item} />
              ))}
            </div>
          </section>
        )}
      </div>

      <Footer />
    </div>
  );
}
