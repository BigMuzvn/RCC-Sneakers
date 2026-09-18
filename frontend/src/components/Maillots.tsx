import { useMemo, useState } from 'react';
import Navbar from './Navbar';
import Footer from './Footer';
import JerseyCard from './JerseyCard';
import Chip from './Chip';
import CatalogueFallback from './CatalogueFallback';
import maillotsHero from '../assets/maillots-hero.png';
import { leaguesOf } from '../api/catalogue';
import { useCatalogue } from '../context/catalogue-context';

const PAGE_GRADIENT =
  'radial-gradient(ellipse 95% 58% at 55% 0%, #33407E 0%, #2B2740 28%, #1A1722 58%, #0B0B10 100%)';

export default function Maillots() {
  const { jerseys: catalogue } = useCatalogue();
  const [league, setLeague] = useState<string>('all');

  // Les championnats sont déduits du catalogue : en ajouter un dans
  // l'administration suffit à le voir apparaître dans les filtres.
  const leagues = useMemo(() => leaguesOf(catalogue), [catalogue]);

  const jerseys = useMemo(
    () => (league === 'all' ? catalogue : catalogue.filter((jersey) => jersey.league === league)),
    [catalogue, league],
  );

  return (
    <div className="relative flex min-h-[100svh] w-full flex-col overflow-x-clip">
      <div aria-hidden="true" className="fixed inset-0 z-0" style={{ background: PAGE_GRADIENT }} />

      {/* broad wash lifting the header band, blaugrana tones sampled from the visual */}
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-x-0 top-0 z-0 h-[520px] sm:h-[680px]"
        style={{
          background:
            'linear-gradient(to bottom, rgba(43,75,168,0.22) 0%, rgba(158,32,56,0.12) 40%, rgba(43,75,168,0.05) 66%, transparent 100%)',
        }}
      />

      <div className="relative z-10 flex-1 px-4 py-4 sm:px-8 sm:py-6 lg:px-14 lg:py-8">
        <Navbar />

        {/* ---------- HEADER: title on the left, jersey facing it ---------- */}
        {/* title block is kept identical to the other pages so every page's h1 lands on the same line */}
        <header className="relative mt-14 select-none sm:mt-20 lg:min-h-[250px]">
          <div className="relative w-fit">
            <span
              aria-hidden="true"
              className="absolute bottom-full left-0 mb-[-0.45em] font-display uppercase leading-none tracking-[0.3em] text-white/25 text-[clamp(11px,2.6vw,34px)]"
            >
              RCC
            </span>
            <h1 className="whitespace-nowrap font-display uppercase leading-none tracking-[-0.02em] text-white/[0.16] text-[clamp(38px,11vw,142px)]">
              Maillots
            </h1>
          </div>

          <p className="mt-5 max-w-md text-[11px] leading-[1.7] text-white/55 sm:mt-6 sm:text-xs">
            Maillots de clubs et de sélections{catalogue[0] ? `, saison ${catalogue[0].season}` : ''}. Flocage nom et
            numéro possible sur demande,
            livraison à Cotonou sous 24 h.
          </p>

          {/* the visual, lit by its own colorway — beside the title on desktop, under it on smaller screens */}
          <div className="relative mt-8 flex justify-center lg:absolute lg:right-0 lg:top-[-4.5rem] lg:mt-0 lg:w-[33%]">
            <div
              aria-hidden="true"
              className="absolute inset-0 -m-10"
              style={{
                background:
                  'radial-gradient(ellipse 62% 62% at 50% 50%, rgba(43,75,168,0.55) 0%, rgba(158,32,56,0.30) 34%, rgba(43,75,168,0.10) 58%, transparent 76%)',
              }}
            />
            <img
              src={maillotsHero}
              alt="Maillots FC Barcelone et Real Madrid saison 2025/26"
              className="relative w-full max-w-[420px] object-contain drop-shadow-[0_30px_45px_rgba(0,0,0,0.55)] lg:max-w-none"
            />
          </div>
        </header>

        {/* ---------- FILTERS ---------- */}
        <div className="-mx-4 mt-8 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:mt-10 sm:flex-wrap sm:px-0 [&::-webkit-scrollbar]:hidden">
          <Chip active={league === 'all'} onClick={() => setLeague('all')}>
            Tous les championnats
          </Chip>
          {leagues.map((item) => (
            <Chip key={item} active={league === item} onClick={() => setLeague(item)}>
              {item}
            </Chip>
          ))}
        </div>

        <p className="mt-6 text-[10px] uppercase tracking-[0.2em] text-white/40">
          {jerseys.length} {jerseys.length > 1 ? 'maillots' : 'maillot'}
        </p>

        {/* ---------- GRID ---------- */}
        {jerseys.length > 0 ? (
          <div className="mt-4 grid grid-cols-2 gap-3 pb-6 sm:gap-5 lg:grid-cols-3 xl:grid-cols-4">
            {jerseys.map((jersey) => (
              <JerseyCard key={jersey.id} jersey={jersey} />
            ))}
          </div>
        ) : (
          <CatalogueFallback vide="Aucun maillot ne correspond à ce championnat." />
        )}
      </div>

      <Footer />
    </div>
  );
}
