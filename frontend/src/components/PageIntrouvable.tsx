import { useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import Navbar from './Navbar';
import Footer from './Footer';

/**
 * Ce que voit quelqu'un qui arrive sur une adresse qui n'existe pas.
 *
 * Il n'y avait rien : aucune route ne correspondant, l'application n'affichait
 * simplement rien — une page blanche, sans barre de navigation ni pied de page,
 * sans un lien pour repartir. Ce n'est pas un cas d'école : c'est ce qui arrive
 * à une adresse recopiée de travers depuis WhatsApp, à un ancien lien, à une
 * faute de frappe. Le visiteur croit le site cassé et ferme l'onglet.
 *
 * La page est donc un carrefour, pas une impasse : elle dit ce qui s'est passé,
 * rappelle l'adresse demandée — souvent la faute saute aux yeux — et propose
 * les trois endroits où l'on voulait probablement aller.
 */
const PAGE_GRADIENT =
  'radial-gradient(ellipse 95% 60% at 50% 0%, #3C4450 0%, #2A2F38 30%, #181B20 62%, #0A0B0D 100%)';

const PISTES = [
  { to: '/', label: 'Accueil', detail: 'Les paires du moment' },
  { to: '/boutique', label: 'Boutique', detail: 'Toutes les sneakers' },
  { to: '/maillots', label: 'Maillots', detail: 'Clubs et sélections' },
];

export default function PageIntrouvable() {
  const { pathname } = useLocation();

  useEffect(() => {
    window.scrollTo(0, 0);
  }, []);

  return (
    <div className="relative flex min-h-[100svh] w-full flex-col overflow-x-clip">
      <div aria-hidden="true" className="fixed inset-0 z-0" style={{ background: PAGE_GRADIENT }} />

      <div className="relative z-10 flex-1 px-4 py-4 sm:px-8 sm:py-6 lg:px-14 lg:py-8">
        <Navbar />

        <header className="relative mt-14 select-none sm:mt-20">
          <div className="relative w-fit">
            <span
              aria-hidden="true"
              className="absolute bottom-full left-0 mb-[-0.45em] font-display uppercase leading-none tracking-[0.3em] text-white/25 text-[clamp(11px,2.6vw,34px)]"
            >
              RCC
            </span>
            <h1 className="whitespace-nowrap font-display uppercase leading-none tracking-[-0.02em] text-white/[0.16] text-[clamp(38px,11vw,142px)]">
              404
            </h1>
          </div>

          <p className="mt-5 max-w-lg text-[11px] leading-[1.7] text-white/55 sm:mt-6 sm:text-xs">
            Cette page n'existe pas. Elle a peut-être été déplacée, ou l'adresse comporte une faute de frappe.
          </p>

          {/* L'adresse demandée est rappelée : neuf fois sur dix, la faute se
              voit d'elle-même une fois qu'on la relit. */}
          <p className="mt-3 max-w-lg break-all font-mono text-[11px] text-white/30">{pathname}</p>
        </header>

        <div className="mt-8 grid grid-cols-1 gap-3 pb-10 sm:mt-10 sm:max-w-2xl sm:grid-cols-3 sm:gap-4">
          {PISTES.map((piste) => (
            <Link
              key={piste.to}
              to={piste.to}
              className="group border border-white/10 bg-white/[0.03] px-5 py-5 transition-colors hover:border-white/35 hover:bg-white/[0.06]"
            >
              <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-[#EDEFF2]">{piste.label}</p>
              <p className="mt-1.5 text-[10px] leading-[1.6] text-white/40">{piste.detail}</p>
            </Link>
          ))}
        </div>

        <p className="pb-6 text-[11px] leading-[1.7] text-white/40">
          Vous cherchiez une paire précise ?{' '}
          <Link to="/contact" className="text-white/70 underline underline-offset-4 transition-colors hover:text-white">
            Écrivez-nous
          </Link>
          , nous la trouvons.
        </p>
      </div>

      <Footer />
    </div>
  );
}
