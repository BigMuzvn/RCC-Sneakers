import { useState } from 'react';
import { Link, NavLink } from 'react-router-dom';
import { Menu, Search, ShoppingCart, User, X } from 'lucide-react';
import rccLogo from '../assets/rcc-logo.png';
import { useCart } from '../context/cart-context';
import { useAuth } from '../context/auth-context';

const NAV_LINKS = [
  { label: 'Accueil', to: '/' },
  { label: 'Boutique', to: '/boutique' },
  { label: 'Maillots', to: '/maillots' },
  { label: 'Soldes', to: '/soldes' },
  { label: 'Contacts', to: '/contact' },
];

export default function Navbar() {
  const [menuOpen, setMenuOpen] = useState(false);
  const { count, openCart } = useCart();
  const { customer } = useAuth();

  return (
    <>
      <header className="relative z-20 flex shrink-0 items-center justify-between gap-4">
        <Link to="/" className="flex flex-col items-center" aria-label="RCC Sneakers — accueil">
          <img src={rccLogo} alt="" className="h-8 w-8 object-contain sm:h-9 sm:w-9" />
          <span className="mt-1 font-display text-[9px] tracking-[0.18em] text-white sm:text-[10px]">RCC</span>
        </Link>

        <nav className="hidden items-center gap-7 lg:flex xl:gap-10">
          {NAV_LINKS.map((link) => (
            <NavLink
              key={link.label}
              to={link.to}
              className={({ isActive }) =>
                `text-[11px] font-semibold uppercase tracking-[0.22em] transition-colors hover:text-white ${
                  isActive && link.to !== '#' ? 'text-white' : 'text-white/90'
                }`
              }
            >
              {link.label}
            </NavLink>
          ))}
        </nav>

        <div className="flex items-center gap-4 sm:gap-5">
          <button type="button" aria-label="Rechercher" className="text-white/90 transition-colors hover:text-white">
            <Search className="h-[18px] w-[18px]" strokeWidth={2} />
          </button>
          <button
            type="button"
            onClick={openCart}
            aria-label={count > 0 ? `Panier, ${count} article${count > 1 ? 's' : ''}` : 'Panier'}
            className="relative text-white/90 transition-colors hover:text-white"
          >
            <ShoppingCart className="h-[18px] w-[18px]" strokeWidth={2} />
            {count > 0 && (
              <span className="absolute -right-2 -top-2 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-[#EDEFF2] px-1 text-[9px] font-bold text-[#17191C]">
                {count}
              </span>
            )}
          </button>
          {/* Connecté, l'icône mène à l'espace client : renvoyer quelqu'un vers
              un formulaire de connexion qu'il a déjà rempli n'a pas de sens. */}
          <Link
            to={customer ? '/espace-client' : '/compte'}
            aria-label={customer ? 'Mon espace client' : 'Mon compte'}
            className={`relative flex h-6 w-6 items-center justify-center rounded-full transition-opacity hover:opacity-80 ${
              customer ? 'bg-[#EDEFF2] text-[#17191C]' : 'bg-[#EDEFF2] text-[#17191C]'
            }`}
          >
            <User className="h-3.5 w-3.5" strokeWidth={2.4} />
            {/* Pastille de rappel : l'adresse n'est pas vérifiée. */}
            {customer && !customer.email_verified && (
              <span
                aria-hidden="true"
                className="absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full border border-[#0B0C0E] bg-[#E2B04A]"
              />
            )}
          </Link>
          <button
            type="button"
            aria-label="Ouvrir le menu"
            aria-expanded={menuOpen}
            onClick={() => setMenuOpen(true)}
            className="text-white/90 transition-colors hover:text-white lg:hidden"
          >
            <Menu className="h-[22px] w-[22px]" strokeWidth={2} />
          </button>
        </div>
      </header>

      <div
        className={`fixed inset-0 z-50 flex flex-col bg-[#07080A] px-4 py-4 transition-opacity duration-300 ease-out motion-reduce:transition-none lg:hidden ${
          menuOpen ? 'opacity-100' : 'pointer-events-none opacity-0'
        }`}
        aria-hidden={!menuOpen}
      >
        <div className="flex shrink-0 items-center justify-between">
          <div className="flex flex-col items-center">
            <img src={rccLogo} alt="" className="h-8 w-8 object-contain" />
            <span className="mt-1 font-display text-[9px] tracking-[0.18em] text-white">RCC</span>
          </div>
          <button
            type="button"
            aria-label="Fermer le menu"
            onClick={() => setMenuOpen(false)}
            className="text-white/90 transition-colors hover:text-white"
          >
            <X className="h-6 w-6" strokeWidth={2} />
          </button>
        </div>

        <nav className="mt-12 flex flex-col gap-7">
          {NAV_LINKS.map((link) => (
            <Link
              key={link.label}
              to={link.to}
              onClick={() => setMenuOpen(false)}
              tabIndex={menuOpen ? 0 : -1}
              className="text-xl font-bold uppercase tracking-[0.18em] text-white/90 transition-colors hover:text-white"
            >
              {link.label}
            </Link>
          ))}
        </nav>
      </div>
    </>
  );
}
