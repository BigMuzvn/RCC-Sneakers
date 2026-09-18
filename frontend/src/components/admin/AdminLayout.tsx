import { useCallback, useEffect, useState } from 'react';
import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { Menu, X } from 'lucide-react';
import AdminSidebar from './AdminSidebar';
import { api } from '../../api/client';
import { useAuth } from '../../context/auth-context';

/** Ce que la vue d'ensemble renvoie et dont la barre latérale a besoin. */
type Overview = {
  orders: { to_handle: number };
  inbox: { new_messages: number };
};

/**
 * Cadre de l'administration.
 *
 * Fond sombre et angles vifs, comme le site public : un client à qui on montre
 * la boutique puis l'administration doit sentir que c'est la même maison.
 */
export default function AdminLayout() {
  const { customer, loading } = useAuth();
  const location = useLocation();
  const [menuOpen, setMenuOpen] = useState(false);
  const [badges, setBadges] = useState<{ orders: number; messages: number }>({ orders: 0, messages: 0 });

  // Les compteurs de la barre latérale se rafraîchissent à chaque changement
  // d'écran : traiter une commande doit faire baisser la pastille, sans quoi
  // elle réclame une action déjà faite.
  const refreshBadges = useCallback(() => {
    api<Overview>('/admin/overview')
      .then((data) => setBadges({ orders: data.orders.to_handle, messages: data.inbox.new_messages }))
      .catch(() => undefined);
  }, []);

  useEffect(() => {
    if (customer?.is_admin) refreshBadges();
  }, [customer, location.pathname, refreshBadges]);

  useEffect(() => setMenuOpen(false), [location.pathname]);

  if (loading) {
    return (
      <div className="flex min-h-[100svh] items-center justify-center bg-[#08090B]">
        <p className="text-[11px] uppercase tracking-[0.2em] text-white/40">Chargement…</p>
      </div>
    );
  }

  // Un visiteur part se connecter ; un client identifié mais non administrateur
  // n'a rien à faire ici et retourne à la boutique — l'y renvoyer vers la
  // connexion l'enfermerait dans une boucle.
  if (!customer) return <Navigate to="/compte?suite=/admin" replace />;
  if (!customer.is_admin) return <Navigate to="/" replace />;

  return (
    <div className="flex min-h-[100svh] w-full bg-[#111316]">
      {/* barre latérale, fixe à partir de lg */}
      <aside className="hidden w-[248px] shrink-0 lg:block">
        <div className="fixed inset-y-0 left-0 w-[248px]">
          <AdminSidebar badges={badges} />
        </div>
      </aside>

      {/* tiroir sur petits écrans */}
      <div
        className={`fixed inset-0 z-50 lg:hidden ${menuOpen ? '' : 'pointer-events-none'}`}
        aria-hidden={!menuOpen}
      >
        <div
          onClick={() => setMenuOpen(false)}
          className={`absolute inset-0 bg-black/60 transition-opacity duration-300 motion-reduce:transition-none ${
            menuOpen ? 'opacity-100' : 'opacity-0'
          }`}
        />
        <div
          className={`absolute inset-y-0 left-0 w-[268px] max-w-[85vw] transition-transform duration-300 ease-out motion-reduce:transition-none ${
            menuOpen ? 'translate-x-0' : '-translate-x-full'
          }`}
        >
          <AdminSidebar badges={badges} onNavigate={() => setMenuOpen(false)} />
          <button
            type="button"
            onClick={() => setMenuOpen(false)}
            aria-label="Fermer le menu"
            className="absolute right-3 top-5 text-white/50 transition-colors hover:text-white"
          >
            <X className="h-5 w-5" strokeWidth={2} />
          </button>
        </div>
      </div>

      <main className="min-w-0 flex-1">
        <header className="flex items-center gap-3 border-b border-white/10 px-4 py-4 lg:hidden">
          <button
            type="button"
            onClick={() => setMenuOpen(true)}
            aria-label="Ouvrir le menu"
            className="text-white/80 transition-colors hover:text-white"
          >
            <Menu className="h-5 w-5" strokeWidth={2} />
          </button>
          <span className="font-display text-[12px] uppercase tracking-[0.18em] text-[#EDEFF2]">
            RCC — Administration
          </span>
        </header>

        <div className="px-4 py-6 sm:px-7 sm:py-8 lg:px-10 lg:py-9">
          <Outlet context={{ refreshBadges }} />
        </div>
      </main>
    </div>
  );
}
