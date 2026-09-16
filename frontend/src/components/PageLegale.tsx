import { useEffect } from 'react';
import { Link, Navigate } from 'react-router-dom';
import Navbar from './Navbar';
import Footer from './Footer';
import HangingShoe from './HangingShoe';
import hangingDunks from '../assets/hanging-dunks.png';
import { LEGAL_PAGES, LEGAL_SLUGS } from '../data/legal';

const PAGE_GRADIENT =
  'radial-gradient(ellipse 95% 58% at 58% 0%, #2E5A55 0%, #26343F 28%, #181C21 58%, #0A0C0D 100%)';

export default function PageLegale({ slug }: { slug: string }) {
  const page = LEGAL_PAGES[slug];

  useEffect(() => {
    window.scrollTo(0, 0);
  }, [slug]);

  if (!page) return <Navigate to="/" replace />;

  return (
    <div className="relative flex min-h-[100svh] w-full flex-col overflow-x-clip">
      <div aria-hidden="true" className="fixed inset-0 z-0" style={{ background: PAGE_GRADIENT }} />
      {/* Dunks held by the laces — mint and royal blue sampled from the colorways */}
      <HangingShoe image={hangingDunks} primary="#3E9E86" secondary="#1E3AA0" />

      <div className="relative z-10 flex-1 px-4 py-4 sm:px-8 sm:py-6 lg:px-14 lg:py-8">
        <Navbar />

        {/* same title lockup as the other inner pages */}
        <header className="relative mt-14 select-none sm:mt-20">
          <div className="relative w-fit">
            <span
              aria-hidden="true"
              /* smaller negative offset than the other pages: this h1 is smaller, so its cap top
                 sits closer to its box top — the label must ride higher to keep the same visual gap */
              className="absolute bottom-full left-0 mb-[-0.21em] font-display uppercase leading-none tracking-[0.3em] text-white/25 text-[clamp(11px,2.6vw,34px)]"
            >
              RCC
            </span>
            <h1 className="font-display uppercase leading-[0.95] tracking-[-0.02em] text-white/[0.16] text-[clamp(30px,7vw,92px)]">
              {page.title}
            </h1>
          </div>

          <p className="mt-5 max-w-lg text-[11px] leading-[1.7] text-white/55 sm:mt-6 sm:text-xs">{page.lead}</p>
          <p className="mt-3 text-[10px] uppercase tracking-[0.18em] text-white/35">
            Dernière mise à jour : {page.updated}
          </p>
        </header>

        <div className="mt-10 grid grid-cols-1 gap-8 pb-8 lg:mt-14 lg:grid-cols-[200px_minmax(0,760px)] lg:gap-16">
          {/* other legal pages */}
          <nav aria-label="Pages légales" className="lg:sticky lg:top-8 lg:self-start">
            <h2 className="text-[10px] font-bold uppercase tracking-[0.2em] text-[#EDEFF2]">Informations</h2>
            <ul className="mt-4 flex flex-wrap gap-x-4 gap-y-2 lg:flex-col lg:gap-2.5">
              {LEGAL_SLUGS.map((key) => (
                <li key={key}>
                  <Link
                    to={`/${key}`}
                    aria-current={key === slug ? 'page' : undefined}
                    className={`text-[11px] transition-colors hover:text-white ${
                      key === slug ? 'text-[#EDEFF2]' : 'text-white/50'
                    }`}
                  >
                    {LEGAL_PAGES[key].navLabel}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>

          {/* content */}
          <div className="flex flex-col gap-8">
            {page.sections.map((section) => (
              <section key={section.heading}>
                <h2 className="text-[11px] font-bold uppercase tracking-[0.18em] text-[#EDEFF2] sm:text-xs">
                  {section.heading}
                </h2>
                {section.paragraphs?.map((paragraph) => (
                  <p key={paragraph} className="mt-3 text-[11px] leading-[1.8] text-white/60 sm:text-xs">
                    {paragraph}
                  </p>
                ))}
                {section.list && (
                  <ul className="mt-3 flex flex-col gap-2">
                    {section.list.map((item) => (
                      <li key={item} className="flex gap-2.5 text-[11px] leading-[1.7] text-white/60 sm:text-xs">
                        <span aria-hidden="true" className="mt-[7px] h-1 w-1 shrink-0 rounded-full bg-white/35" />
                        <span>{item}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </section>
            ))}

            <p className="border-t border-white/10 pt-6 text-[10px] leading-[1.7] text-white/35">
              Une question sur cette page ?{' '}
              <Link to="/contact" className="text-white/60 underline underline-offset-2 transition-colors hover:text-white">
                Écrivez-nous
              </Link>
              .
            </p>
          </div>
        </div>
      </div>

      <Footer />
    </div>
  );
}
