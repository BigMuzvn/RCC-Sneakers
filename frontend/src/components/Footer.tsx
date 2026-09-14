import { Link } from 'react-router-dom';
import { Mail, MapPin, Phone } from 'lucide-react';
import rccLogo from '../assets/rcc-logo.png';

const SOCIALS = ['Instagram', 'Facebook', 'WhatsApp'];

export default function Footer() {
  return (
    <footer className="relative mt-8 overflow-hidden border-t border-white/10 bg-[#07080A]">
      <div className="relative z-10 px-4 pb-8 pt-12 sm:px-8 sm:pt-14 lg:px-14">
        <div className="grid grid-cols-2 gap-x-6 gap-y-10 lg:grid-cols-4 lg:gap-10">
          {/* brand */}
          <div className="col-span-2 lg:col-span-1">
            <Link to="/" className="flex w-fit flex-col items-center" aria-label="RCC Sneakers — accueil">
              <img src={rccLogo} alt="" className="h-9 w-9 object-contain" />
              <span className="mt-1 font-display text-[10px] tracking-[0.18em] text-white">RCC</span>
            </Link>
            <p className="mt-4 max-w-xs text-[11px] leading-[1.75] text-white/50">
              Sneakers authentiques, sélectionnées à la main et livrées à Cotonou sous 24 h. Chaque paire est
              contrôlée avant expédition.
            </p>
            <div className="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2">
              {SOCIALS.map((label) => (
                <a
                  key={label}
                  href="#"
                  className="text-[10px] font-bold uppercase tracking-[0.16em] text-white/55 transition-colors hover:text-white"
                >
                  {label}
                </a>
              ))}
            </div>
          </div>

          {/* navigation */}
          <div>
            <h2 className="text-[10px] font-bold uppercase tracking-[0.2em] text-[#EDEFF2]">Navigation</h2>
            <ul className="mt-4 flex flex-col gap-2.5">
              {[
                { label: 'Accueil', to: '/' },
                { label: 'Boutique', to: '/boutique' },
                { label: 'Maillots', to: '/maillots' },
                { label: 'Soldes', to: '/soldes' },
                { label: 'Contact', to: '/contact' },
              ].map((link) => (
                <li key={link.label}>
                  <Link to={link.to} className="text-[11px] text-white/50 transition-colors hover:text-white">
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          {/* categories */}
          <div>
            <h2 className="text-[10px] font-bold uppercase tracking-[0.2em] text-[#EDEFF2]">Catégories</h2>
            <ul className="mt-4 flex flex-col gap-2.5">
              {['Lifestyle', 'Running', 'Basketball'].map((label) => (
                <li key={label}>
                  <Link to="/boutique" className="text-[11px] text-white/50 transition-colors hover:text-white">
                    {label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          {/* contact */}
          <div className="col-span-2 lg:col-span-1">
            <h2 className="text-[10px] font-bold uppercase tracking-[0.2em] text-[#EDEFF2]">Nous joindre</h2>
            <ul className="mt-4 flex flex-col gap-3 text-[11px] text-white/50">
              <li className="flex items-start gap-2.5">
                <MapPin className="mt-px h-3.5 w-3.5 shrink-0 text-white/40" strokeWidth={2} />
                <span>Cotonou, Bénin</span>
              </li>
              <li className="flex items-start gap-2.5">
                <Phone className="mt-px h-3.5 w-3.5 shrink-0 text-white/40" strokeWidth={2} />
                <span>+229 01 00 00 00 00</span>
              </li>
              <li className="flex items-start gap-2.5">
                <Mail className="mt-px h-3.5 w-3.5 shrink-0 text-white/40" strokeWidth={2} />
                <span>contact@rccsneakers.bj</span>
              </li>
            </ul>
          </div>
        </div>

        <div className="mt-12 flex flex-col gap-2 border-t border-white/10 pt-6 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-[10px] uppercase tracking-[0.14em] text-white/35">
            © {new Date().getFullYear()} RCC Sneakers — Cotonou, Bénin
          </p>
          <p className="text-[10px] uppercase tracking-[0.14em] text-white/35">Paiement mobile money & espèces</p>
        </div>
      </div>

      {/* closing signature, same lockup as the hero */}
      <div
        aria-hidden="true"
        className="pointer-events-none relative z-0 -mb-[0.24em] mt-2 select-none px-4 sm:px-8 lg:px-14"
      >
        <span className="block font-display uppercase leading-none tracking-[0.3em] text-white/[0.06] text-[clamp(10px,2.2vw,28px)]">
          RCC
        </span>
        <span className="block whitespace-nowrap font-display uppercase leading-none tracking-[-0.02em] text-white/[0.05] text-[clamp(40px,13vw,170px)]">
          Sneakers
        </span>
      </div>
    </footer>
  );
}
