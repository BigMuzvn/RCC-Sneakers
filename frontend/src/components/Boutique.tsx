import { useMemo, useState } from 'react';
import Navbar from './Navbar';
import Footer from './Footer';
import HangingShoe from './HangingShoe';
import ProductCard from './ProductCard';
import Chip from './Chip';
import CatalogueFallback from './CatalogueFallback';
import hangingJordan from '../assets/hanging-jordan.png';
import { CATEGORIES, brandsOf, type Category } from '../api/catalogue';
import { useCatalogue } from '../context/catalogue-context';

const PAGE_GRADIENT =
  'radial-gradient(ellipse 95% 60% at 62% 0%, #5A4A46 0%, #3A3034 30%, #1E1C20 60%, #0C0C0E 100%)';

type CategoryFilter = Category | 'all';

export default function Boutique() {
  const { products: catalogue } = useCatalogue();
  const [brand, setBrand] = useState<string>('all');
  const [category, setCategory] = useState<CategoryFilter>('all');

  // Les marques viennent du catalogue lui-même : celle d'une paire ajoutée
  // dans l'administration doit apparaître ici sans qu'on y touche.
  const brands = useMemo(() => brandsOf(catalogue), [catalogue]);

  const products = useMemo(
    () =>
      catalogue.filter(
        (product) =>
          (brand === 'all' || product.brand === brand) &&
          (category === 'all' || product.category === category),
      ),
    [catalogue, brand, category],
  );

  return (
    <div className="relative flex min-h-[100svh] w-full flex-col overflow-x-clip">
      <div aria-hidden="true" className="fixed inset-0 z-0" style={{ background: PAGE_GRADIENT }} />
      {/* Jordan 1 Chicago — orange box + Chicago red */}
      <HangingShoe image={hangingJordan} primary="#F0481B" secondary="#C8142E" />

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
              Boutique
            </h1>
          </div>

          <p className="mt-5 max-w-md text-[11px] leading-[1.7] text-white/55 sm:mt-6 sm:text-xs">
            {catalogue.length > 0 ? `${catalogue.length} paires authentiques` : 'Des paires authentiques'} sélectionnées
            à la main. Livraison à Cotonou sous 24 h et expédition dans tout le Bénin.
          </p>
        </header>

        {/* ---------- FILTERS ---------- */}
        <div className="mt-8 flex flex-col gap-3 sm:mt-10">
          <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:px-0 [&::-webkit-scrollbar]:hidden">
            <Chip active={brand === 'all'} onClick={() => setBrand('all')}>
              Toutes les marques
            </Chip>
            {brands.map((item) => (
              <Chip key={item} active={brand === item} onClick={() => setBrand(item)}>
                {item}
              </Chip>
            ))}
          </div>

          <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:px-0 [&::-webkit-scrollbar]:hidden">
            <Chip active={category === 'all'} onClick={() => setCategory('all')}>
              Tout
            </Chip>
            {CATEGORIES.map((item) => (
              <Chip key={item.id} active={category === item.id} onClick={() => setCategory(item.id)}>
                {item.label}
              </Chip>
            ))}
          </div>
        </div>

        <p className="mt-6 text-[10px] uppercase tracking-[0.2em] text-white/40">
          {products.length} {products.length > 1 ? 'modèles' : 'modèle'}
        </p>

        {/* ---------- GRID ---------- */}
        {products.length > 0 ? (
          <div className="mt-4 grid grid-cols-2 gap-3 pb-6 sm:gap-5 lg:grid-cols-3 xl:grid-cols-4">
            {products.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        ) : (
          <CatalogueFallback vide="Aucune paire ne correspond à cette combinaison de filtres." />
        )}
      </div>

      <Footer />
    </div>
  );
}
