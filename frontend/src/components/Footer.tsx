import { useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowRight, Check, Mail, MapPin, Phone } from 'lucide-react';
import rccLogo from '../assets/rcc-logo.png';
import { LEGAL_PAGES, LEGAL_SLUGS } from '../data/legal';
import { useCatalogue } from '../context/catalogue-context';

/** Réseaux affichés, dans cet ordre, et seulement si une adresse est renseignée. */
const SOCIALS = [
  { key: 'social_instagram', label: 'Instagram' },
  { key: 'social_facebook', label: 'Facebook' },
  { key: 'social_whatsapp', label: 'WhatsApp' },
] as const;

function Newsletter() {
  const [signedUp, setSignedUp] = useState(false);

  return (
    <div className="border-y border-white/10 py-8 sm:py-10">
      <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between lg:gap-12">
        <div className="max-w-md">
          <h2 className="text-[11px] font-bold uppercase tracking-[0.2em] text-[#EDEFF2]">
            Les drops avant tout le monde
          </h2>
          <p className="mt-2 text-[11px] leading-[1.7] text-white/50">
            Nouvelles arrivées, restocks et ventes privées. Un message quand il y a vraiment quelque chose, jamais plus.
          </p>
        </div>

        {signedUp ? (
          <div className="flex items-center gap-3 lg:w-[420px]">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#EDEFF2] text-[#17191C]">
              <Check className="h-4 w-4" strokeWidth={2.5} />
            </span>
            <p className="text-[11px] leading-[1.6] text-white/70">
              Inscription enregistrée. À très vite pour le prochain drop.
            </p>
          </div>
        ) : (
          <form
            className="flex w-full items-stretch gap-2 lg:w-[420px]"
            onSubmit={(event) => {
              event.preventDefault();
              // TODO: POST /api/newsletter — nothing is stored yet
              setSignedUp(true);
            }}
          >
            <label htmlFor="newsletter-email" className="sr-only">
              Votre adresse e-mail
            </label>
            <input
              required
              id="newsletter-email"
              type="email"
              name="email"
              autoComplete="email"
              placeholder="vous@exemple.com"
              className="min-w-0 flex-1 border border-white/15 bg-white/[0.03] px-3.5 py-3 text-[12px] text-white placeholder:text-white/35 transition-colors focus:border-white/50 focus:outline-none"
            />
            <button
              type="submit"
              aria-label="S’inscrire à la lettre d’information"
              className="flex shrink-0 items-center gap-2 bg-white px-4 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-[#141516] transition-opacity hover:opacity-90 sm:px-6"
            >
              <span className="hidden sm:inline">S’inscrire</span>
              <ArrowRight className="h-4 w-4" strokeWidth={2.2} />
            </button>
          </form>
        )}
      </div>
    </div>
  );
}

export default function Footer() {
  const { settings } = useCatalogue();

  return (
    <footer className="relative mt-8 overflow-hidden border-t border-white/10 bg-[#07080A]">
      <div className="relative z-10 px-4 pb-8 pt-12 sm:px-8 sm:pt-14 lg:px-14">
        <Newsletter />

        <div className="mt-12 grid grid-cols-2 gap-x-6 gap-y-10 lg:grid-cols-4 lg:gap-10">
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
            {/* Un réseau sans adresse n'est pas affiché : un lien qui ne mène
                nulle part coûte plus de confiance qu'il n'en rapporte. */}
            <div className="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2">
              {SOCIALS.filter(({ key }) => settings[key] !== '').map(({ key, label }) => (
                <a
                  key={key}
                  href={settings[key]}
                  target="_blank"
                  rel="noreferrer noopener"
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
            {/* Ces trois lignes viennent des réglages : le gérant change son
                numéro depuis l'administration, sans redéploiement. Le téléphone
                et l'adresse sont cliquables — sur un téléphone, c'est la
                différence entre un appel et un numéro à recopier. */}
            <ul className="mt-4 flex flex-col gap-3 text-[11px] text-white/50">
              {settings.shop_city !== '' && (
                <li className="flex items-start gap-2.5">
                  <MapPin className="mt-px h-3.5 w-3.5 shrink-0 text-white/40" strokeWidth={2} />
                  <span>{settings.shop_city}</span>
                </li>
              )}
              {settings.shop_phone !== '' && (
                <li className="flex items-start gap-2.5">
                  <Phone className="mt-px h-3.5 w-3.5 shrink-0 text-white/40" strokeWidth={2} />
                  <a href={`tel:${settings.shop_phone.replace(/\s/g, '')}`} className="transition-colors hover:text-white">
                    {settings.shop_phone}
                  </a>
                </li>
              )}
              {settings.shop_email !== '' && (
                <li className="flex items-start gap-2.5">
                  <Mail className="mt-px h-3.5 w-3.5 shrink-0 text-white/40" strokeWidth={2} />
                  <a href={`mailto:${settings.shop_email}`} className="transition-colors hover:text-white">
                    {settings.shop_email}
                  </a>
                </li>
              )}
            </ul>
          </div>
        </div>

        {/* legal pages */}
        <nav aria-label="Informations légales" className="mt-12 border-t border-white/10 pt-6">
          <ul className="flex flex-wrap gap-x-5 gap-y-2">
            {LEGAL_SLUGS.map((slug) => (
              <li key={slug}>
                <Link
                  to={`/${slug}`}
                  className="text-[10px] uppercase tracking-[0.14em] text-white/45 transition-colors hover:text-white"
                >
                  {LEGAL_PAGES[slug].navLabel}
                </Link>
              </li>
            ))}
          </ul>
        </nav>

        <div className="mt-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
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
