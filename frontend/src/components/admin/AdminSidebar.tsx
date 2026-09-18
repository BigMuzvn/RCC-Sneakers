import { NavLink } from 'react-router-dom';
import {
  Boxes,
  LayoutDashboard,
  LogOut,
  Mail,
  Package,
  Settings,
  Shirt,
  Store,
  Users,
} from 'lucide-react';
import { useAuth } from '../../context/auth-context';
import logo from '../../assets/rcc-logo.png';

export const ADMIN_LINKS = [
  { to: '/admin', end: true, label: "Vue d'ensemble", icon: LayoutDashboard },
  { to: '/admin/commandes', label: 'Commandes', icon: Package, badge: 'orders' },
  { to: '/admin/produits', label: 'Sneakers', icon: Boxes },
  { to: '/admin/maillots', label: 'Maillots', icon: Shirt },
  { to: '/admin/clients', label: 'Clients', icon: Users },
  { to: '/admin/messages', label: 'Messagerie', icon: Mail, badge: 'messages' },
  { to: '/admin/vitrine', label: 'Vitrine', icon: Store },
  { to: '/admin/reglages', label: 'Réglages', icon: Settings },
] as const;

type Props = {
  /** Compteurs affichés en pastille, par clé de lien. */
  badges?: Partial<Record<'orders' | 'messages', number>>;
  onNavigate?: () => void;
};

/**
 * Navigation de l'administration.
 *
 * L'élément actif est **découpé dans la barre** plutôt que simplement
 * surligné : une pastille claire qui traverse toute la largeur, et la barre qui
 * se recourbe vers l'intérieur juste au-dessus et juste en dessous.
 *
 * Ces angles concaves n'existent pas en CSS. On les fabrique avec deux
 * pseudo-éléments collés au bord droit : un carré dont un seul coin est
 * arrondi, et dont une `box-shadow` étalée peint la couleur de la barre. L'ombre
 * remplit tout sauf l'arrondi — c'est ce vide qui donne le creux.
 *
 * Tout le reste garde les angles vifs du site public. L'encoche devient ainsi
 * un accent rare, et non une pièce rapportée d'une autre direction artistique.
 */
export default function AdminSidebar({ badges = {}, onNavigate }: Props) {
  const { customer, logout } = useAuth();

  return (
    <nav aria-label="Administration" className="flex h-full flex-col bg-[#0B0C0E]">
      <div className="flex items-center gap-3 px-6 py-6">
        <img src={logo} alt="" className="h-8 w-8 object-contain" />
        <div className="min-w-0">
          <p className="font-display text-[13px] uppercase leading-none tracking-[0.18em] text-[#EDEFF2]">RCC</p>
          <p className="mt-1 text-[9px] uppercase tracking-[0.2em] text-white/35">Administration</p>
        </div>
      </div>

      <ul className="mt-2 flex-1 overflow-y-auto">
        {ADMIN_LINKS.map(({ to, label, icon: Icon, ...rest }) => {
          const badge = 'badge' in rest ? badges[rest.badge as 'orders' | 'messages'] : undefined;

          return (
            <li key={to}>
              <NavLink
                to={to}
                end={'end' in rest ? rest.end : false}
                onClick={onNavigate}
                className={({ isActive }) =>
                  `group relative flex items-center gap-3 py-3.5 pl-6 pr-5 text-[11px] font-bold uppercase tracking-[0.12em] transition-colors duration-200 motion-reduce:transition-none ${
                    isActive ? 'text-[#17191C]' : 'text-white/45 hover:text-white'
                  }`
                }
              >
                {({ isActive }) => (
                  <>
                    {isActive && (
                      <>
                        {/* La pastille va jusqu'au bord droit et s'y arrête.
                            Dans la référence elle débordait sur une zone de
                            contenu blanche, où l'épaulement clair se fondait ;
                            sur fond sombre, ce même débordement ressort comme
                            deux languettes blanches. */}
                        <span
                          aria-hidden="true"
                          className="absolute inset-y-0 left-3 right-0 rounded-l-full bg-[#EDEFF2]"
                        />

                        {/* Les deux creux, peints dans la couleur de la barre.
                            Le carré lui-même est transparent : c'est son ombre
                            étalée qui remplit tout sauf l'arrondi, et c'est ce
                            vide qui donne l'angle concave. */}
                        <span
                          aria-hidden="true"
                          className="pointer-events-none absolute right-0 top-0 h-[16px] w-[16px] rounded-br-[16px] shadow-[5px_5px_0_5px_#0B0C0E]"
                        />
                        <span
                          aria-hidden="true"
                          className="pointer-events-none absolute bottom-0 right-0 h-[16px] w-[16px] rounded-tr-[16px] shadow-[5px_-5px_0_5px_#0B0C0E]"
                        />
                      </>
                    )}

                    <Icon className="relative h-4 w-4 shrink-0" strokeWidth={2.1} />
                    <span className="relative min-w-0 truncate">{label}</span>

                    {badge !== undefined && badge > 0 && (
                      <span
                        className={`relative ml-auto flex h-[18px] min-w-[18px] items-center justify-center rounded-full px-1.5 text-[9px] font-bold ${
                          isActive ? 'bg-[#17191C] text-[#EDEFF2]' : 'bg-[#C8242F] text-white'
                        }`}
                      >
                        {badge > 99 ? '99+' : badge}
                      </span>
                    )}
                  </>
                )}
              </NavLink>
            </li>
          );
        })}
      </ul>

      <div className="border-t border-white/10 px-6 py-5">
        <p className="truncate text-[11px] text-white/70">{customer?.name}</p>
        <p className="mt-0.5 truncate text-[10px] text-white/35">{customer?.email}</p>

        <div className="mt-4 flex flex-col gap-2">
          <NavLink
            to="/"
            onClick={onNavigate}
            className="text-[10px] uppercase tracking-[0.14em] text-white/45 transition-colors hover:text-white"
          >
            ← Retour à la boutique
          </NavLink>
          <button
            type="button"
            onClick={() => void logout()}
            className="flex items-center gap-2 text-[10px] uppercase tracking-[0.14em] text-white/45 transition-colors hover:text-white"
          >
            <LogOut className="h-3 w-3" strokeWidth={2.2} />
            Déconnexion
          </button>
        </div>
      </div>
    </nav>
  );
}
