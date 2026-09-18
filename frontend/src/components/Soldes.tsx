import { useMemo, useState } from 'react';
import Navbar from './Navbar';
import Footer from './Footer';
import HangingShoe from './HangingShoe';
import ProductCard from './ProductCard';
import JerseyCard from './JerseyCard';
import Chip from './Chip';
import CatalogueFallback from './CatalogueFallback';
import hangingSamba from '../assets/hanging-samba.png';
import type { Jersey, Product } from '../api/catalogue';
import { useCatalogue } from '../context/catalogue-context';
import { formatXof } from '../utils/format';

const PAGE_GRADIENT =
  'radial-gradient(ellipse 95% 58% at 58% 0%, #2F5244 0%, #23342E 28%, #171D1B 58%, #0B0D0C 100%)';

const SORTS = [
  { id: 'discount', label: 'Meilleure remise' },
  { id: 'price-asc', label: 'Prix croissant' },
  { id: 'price-desc', label: 'Prix décroissant' },
] as const;

type Sort = (typeof SORTS)[number]['id'];

const FAMILIES = [
  { id: 'all', label: 'Tout' },
  { id: 'sneaker', label: 'Sneakers' },
  { id: 'jersey', label: 'Maillots' },
] as const;

type Family = (typeof FAMILIES)[number]['id'];

/** Dire ce qui manque, et non « aucun article » quand on a filtré soi-même. */
const VIDE: Record<Family, string> = {
  all: 'Rien en solde en ce moment. Les fins de série arrivent par vagues.',
  sneaker: "Aucune paire n'est en solde en ce moment. Essayez « Tout ».",
  jersey: "Aucun maillot n'est en solde en ce moment. Essayez « Tout ».",
};

/**
 * Un article en solde, quelle que soit sa famille.
 *
 * La page ne montrait que des paires, alors qu'un maillot remisé l'est tout
 * autant : le client venu chercher une affaire n'en voyait que la moitié.
 * Réunir les deux impose une forme commune, parce que trier par remise n'a de
 * sens que sur une seule liste — deux grilles côte à côte se compareraient mal.
 */
type Deal = { kind: 'sneaker'; data: Product } | { kind: 'jersey'; data: Jersey };

/** Les seuls champs dont le classement ait besoin, communs aux deux familles. */
const prix = (deal: Deal) => deal.data.price_xof;
const ancien = (deal: Deal) => deal.data.old_price_xof ?? deal.data.price_xof;
const discountOf = (deal: Deal) => (deal.data.old_price_xof ? 1 - prix(deal) / ancien(deal) : 0);

export default function Soldes() {
  const { products, jerseys } = useCatalogue();
  const [sort, setSort] = useState<Sort>('discount');
  const [family, setFamily] = useState<Family>('all');

  const onSale = useMemo<Deal[]>(
    () => [
      ...products
        .filter((product) => product.old_price_xof !== null)
        .map((data) => ({ kind: 'sneaker' as const, data })),
      ...jerseys
        .filter((jersey) => jersey.old_price_xof !== null)
        .map((data) => ({ kind: 'jersey' as const, data })),
    ],
    [products, jerseys],
  );

  const deals = useMemo(() => {
    const list = onSale.filter((deal) => family === 'all' || deal.kind === family);

    if (sort === 'price-asc') return list.sort((a, b) => prix(a) - prix(b));
    if (sort === 'price-desc') return list.sort((a, b) => prix(b) - prix(a));
    return list.sort((a, b) => discountOf(b) - discountOf(a));
  }, [onSale, family, sort]);

  // Les chiffres du bandeau portent sur **tout** ce qui est soldé, jamais sur
  // le filtre en cours : ils annoncent l'ampleur de l'opération, et changer de
  // famille ne doit pas donner l'impression qu'elle rétrécit.
  //
  // `Math.max` d'une liste vide vaut -Infinity : sans ce garde-fou, une page
  // sans solde afficherait « -Infinity % » le temps du chargement.
  const bestDiscount = onSale.length === 0 ? 0 : Math.round(Math.max(...onSale.map(discountOf)) * 100);
  const totalSaved = onSale.reduce((sum, deal) => sum + (ancien(deal) - prix(deal)), 0);

  return (
    <div className="relative flex min-h-[100svh] w-full flex-col overflow-x-clip">
      <div aria-hidden="true" className="fixed inset-0 z-0" style={{ background: PAGE_GRADIENT }} />
      {/* adidas Samba — collegiate green + gum */}
      <HangingShoe image={hangingSamba} primary="#1F7A52" secondary="#8A5C2E" />

      <div className="relative z-10 flex-1 px-4 py-4 sm:px-8 sm:py-6 lg:px-14 lg:py-8">
        <Navbar />

        {/* ---------- TITLE ---------- */}
        <header className="relative mt-14 select-none sm:mt-20">
          <div className="relative w-fit">
            <span
              aria-hidden="true"
              className="absolute bottom-full left-0 mb-[-0.45em] font-display uppercase leading-none tracking-[0.3em] text-white/25 text-[clamp(11px,2.6vw,34px)]"
            >
              RCC
            </span>
            <h1 className="whitespace-nowrap font-display uppercase leading-none tracking-[-0.02em] text-white/[0.16] text-[clamp(38px,11vw,142px)]">
              Soldes
            </h1>
          </div>

          <p className="mt-5 max-w-md text-[11px] leading-[1.7] text-white/55 sm:mt-6 sm:text-xs">
            Paires et maillots en fin de série, au prix le plus bas de la saison. Stock limité — une fois la taille
            partie, elle ne revient pas.
          </p>
        </header>

        {/* ---------- HIGHLIGHTS ---------- */}
        <div className="mt-8 grid grid-cols-2 gap-3 sm:mt-10 sm:max-w-2xl sm:gap-4 lg:grid-cols-3">
          <div className="border border-white/10 bg-white/[0.03] px-4 py-4">
            <p className="font-display text-[26px] leading-none text-[#EDEFF2] sm:text-[32px]">-{bestDiscount}%</p>
            <p className="mt-2 text-[9px] uppercase tracking-[0.16em] text-white/45">Remise maximale</p>
          </div>
          <div className="border border-white/10 bg-white/[0.03] px-4 py-4">
            <p className="font-display text-[26px] leading-none text-[#EDEFF2] sm:text-[32px]">{onSale.length}</p>
            <p className="mt-2 text-[9px] uppercase tracking-[0.16em] text-white/45">Modèles concernés</p>
          </div>
          <div className="col-span-2 border border-white/10 bg-white/[0.03] px-4 py-4 lg:col-span-1">
            <p className="font-display text-[18px] leading-none text-[#EDEFF2] sm:text-[22px]">
              {formatXof(totalSaved)}
            </p>
            <p className="mt-2 text-[9px] uppercase tracking-[0.16em] text-white/45">D'économies cumulées</p>
          </div>
        </div>

        {/* ---------- FILTRES ---------- */}
        <div className="mt-8 flex flex-col gap-3">
          {/* La famille d'abord, le classement ensuite : on choisit ce qu'on
              regarde avant de choisir dans quel ordre le regarder. */}
          <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:px-0 [&::-webkit-scrollbar]:hidden">
            {FAMILIES.map((item) => (
              <Chip key={item.id} active={family === item.id} onClick={() => setFamily(item.id)}>
                {item.label}
              </Chip>
            ))}
          </div>

          <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:px-0 [&::-webkit-scrollbar]:hidden">
            {SORTS.map((item) => (
              <Chip key={item.id} active={sort === item.id} onClick={() => setSort(item.id)}>
                {item.label}
              </Chip>
            ))}
          </div>
        </div>

        <p className="mt-6 text-[10px] uppercase tracking-[0.2em] text-white/40">
          {deals.length} {deals.length > 1 ? 'articles en solde' : 'article en solde'}
        </p>

        {/* ---------- GRILLE ---------- */}
        {deals.length > 0 ? (
          <div className="mt-4 grid grid-cols-2 gap-3 pb-6 sm:gap-5 lg:grid-cols-3 xl:grid-cols-4">
            {deals.map((deal) =>
              deal.kind === 'sneaker' ? (
                <ProductCard key={`sneaker-${deal.data.id}`} product={deal.data} />
              ) : (
                <JerseyCard key={`jersey-${deal.data.id}`} jersey={deal.data} />
              ),
            )}
          </div>
        ) : (
          <CatalogueFallback vide={VIDE[family]} />
        )}
      </div>

      <Footer />
    </div>
  );
}
