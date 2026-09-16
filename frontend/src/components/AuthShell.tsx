import type { ReactNode } from 'react';
import Navbar from './Navbar';
import Footer from './Footer';
import SideShoe from './SideShoe';
import paireCompte from '../assets/paire-compte.png';

const PAGE_GRADIENT =
  'radial-gradient(ellipse 95% 62% at 74% 0%, #4A5878 0%, #3C3140 30%, #1B191E 60%, #0A0A0C 100%)';

/**
 * Gabarit des pages atteintes depuis un e-mail : vérification d'adresse,
 * mot de passe oublié, réinitialisation.
 *
 * Elles partagent l'éclairage de l'espace client — c'est la même destination du
 * point de vue du client, il ne doit pas avoir l'impression de changer de site
 * en cliquant dans son courrier.
 */
export default function AuthShell({
  title,
  lead,
  children,
}: {
  title: string;
  lead?: string;
  children: ReactNode;
}) {
  return (
    <div className="relative flex min-h-[100svh] w-full flex-col overflow-x-clip">
      <div aria-hidden="true" className="fixed inset-0 z-0" style={{ background: PAGE_GRADIENT }} />
      <SideShoe image={paireCompte} primary="#8090B0" secondary="#A06070" />

      <div className="relative z-10 flex-1 px-4 py-4 sm:px-8 sm:py-6 lg:px-14 lg:py-8">
        <Navbar />

        <div className="mx-auto mt-14 w-full max-w-md pb-20 sm:mt-24 lg:mx-0">
          <span className="block font-display text-[11px] uppercase leading-none tracking-[0.3em] text-white/25 sm:text-[13px]">
            RCC
          </span>
          <h1 className="mt-2 font-display uppercase leading-none tracking-[-0.01em] text-[#EDEFF2] text-[clamp(26px,4.4vw,42px)]">
            {title}
          </h1>
          {lead && <p className="mt-3 text-[11px] leading-[1.7] text-white/55 sm:text-xs">{lead}</p>}

          <div className="mt-7">{children}</div>
        </div>
      </div>

      <Footer />
    </div>
  );
}
