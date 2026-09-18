import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { AlertTriangle, Check, Download, Heart, LogOut, Package, User } from 'lucide-react';
import Navbar from './Navbar';
import Footer from './Footer';
import SideShoe from './SideShoe';
import ProductCard from './ProductCard';
import JerseyCard from './JerseyCard';
import { ApiFailure, api } from '../api/client';
import { useAuth } from '../context/auth-context';
import { useFavorites } from '../context/favorites-context';
import { useCatalogue } from '../context/catalogue-context';
import { formatXof } from '../utils/format';
import paireCompte from '../assets/paire-compte.png';

/** Tons relevés sur le visuel : périwinkle 30 %, bordeaux-rose 23 %. */
const PAGE_GRADIENT =
  'radial-gradient(ellipse 95% 62% at 74% 0%, #4A5878 0%, #3C3140 30%, #1B191E 60%, #0A0A0C 100%)';

const TABS = [
  { id: 'commandes', label: 'Commandes', icon: Package },
  { id: 'informations', label: 'Informations', icon: User },
  { id: 'favoris', label: 'Favoris', icon: Heart },
] as const;

type Tab = (typeof TABS)[number]['id'];

const inputClass =
  'w-full border border-white/15 bg-white/[0.03] px-3.5 py-3 text-[12px] text-white placeholder:text-white/35 transition-colors focus:border-white/50 focus:outline-none';
const labelClass = 'text-[10px] font-bold uppercase tracking-[0.16em] text-white/60';
const cardClass = 'border border-white/10 bg-white/[0.03] p-5 sm:p-7';

export default function EspaceClient() {
  const navigate = useNavigate();
  const [params, setParams] = useSearchParams();
  const { customer, loading, logout } = useAuth();

  const requested = params.get('volet') as Tab | null;
  const [tab, setTab] = useState<Tab>(
    TABS.some((t) => t.id === requested) ? (requested as Tab) : 'commandes',
  );

  // Redirection seulement une fois la session vérifiée : pendant le chargement,
  // `customer` est null sans que cela signifie « visiteur ».
  //
  // Un administrateur est renvoyé vers son tableau de bord. Ses commandes, ses
  // favoris, ses coordonnées : rien de tout cela ne concerne son travail ici, et
  // le mot de passe — la seule chose qu'il ait à changer — est dans les réglages.
  useEffect(() => {
    if (loading) return;
    if (!customer) navigate('/compte', { replace: true });
    else if (customer.is_admin) navigate('/admin', { replace: true });
  }, [loading, customer, navigate]);

  const selectTab = (next: Tab) => {
    setTab(next);
    setParams(next === 'commandes' ? {} : { volet: next }, { replace: true });
  };

  if (loading || !customer || customer.is_admin) {
    return (
      <div className="relative flex min-h-[100svh] w-full items-center justify-center" style={{ background: PAGE_GRADIENT }}>
        <p className="text-[11px] uppercase tracking-[0.2em] text-white/45">Chargement…</p>
      </div>
    );
  }

  return (
    <div className="relative flex min-h-[100svh] w-full flex-col overflow-x-clip">
      <div aria-hidden="true" className="fixed inset-0 z-0" style={{ background: PAGE_GRADIENT }} />
      <SideShoe image={paireCompte} primary="#8090B0" secondary="#A06070" />

      <div className="relative z-10 flex-1 px-4 py-4 sm:px-8 sm:py-6 lg:px-14 lg:py-8">
        <Navbar />

        {/* ---------- TITRE ---------- */}
        <header className="relative mt-14 select-none sm:mt-20">
          <div className="relative w-fit">
            <span
              aria-hidden="true"
              className="absolute bottom-full left-0 mb-[-0.45em] font-display uppercase leading-none tracking-[0.3em] text-white/25 text-[clamp(11px,2.6vw,34px)]"
            >
              RCC
            </span>
            <h1 className="whitespace-nowrap font-display uppercase leading-none tracking-[-0.02em] text-white/[0.16] text-[clamp(30px,8.4vw,110px)]">
              Espace client
            </h1>
          </div>

          <p className="mt-5 max-w-md text-[11px] leading-[1.7] text-white/55 sm:mt-6 sm:text-xs">
            Bonjour {customer.name.split(' ')[0]}. Vos commandes, vos informations et vos paires mises de côté.
          </p>
        </header>

        {/* Gouttière droite : la paire entre par ce bord, et rien de lisible ne
            doit passer dessous. Largeurs calées sur sa taille à chaque palier. */}
        <div className="sm:pr-[210px] lg:pr-[300px] xl:pr-[350px]">
          {!customer.email_verified && <VerificationBanner />}

          {/* ---------- VOLETS ---------- */}
          <div className="mt-8 flex flex-wrap items-center gap-2 sm:mt-10">
          {TABS.map(({ id, label, icon: Icon }) => (
            <button
              key={id}
              type="button"
              onClick={() => selectTab(id)}
              aria-current={tab === id}
              className={`flex items-center gap-2 border px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.14em] transition-colors duration-200 motion-reduce:transition-none ${
                tab === id
                  ? 'border-[#EDEFF2] bg-[#EDEFF2] text-[#17191C]'
                  : 'border-white/15 text-white/60 hover:border-white/40 hover:text-white'
              }`}
            >
              <Icon className="h-3.5 w-3.5" strokeWidth={2.2} />
              {label}
            </button>
          ))}

          <button
            type="button"
            onClick={() => {
              void logout().then(() => navigate('/'));
            }}
            className="ml-auto flex items-center gap-2 border border-white/15 px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.14em] text-white/50 transition-colors hover:border-white/40 hover:text-white"
          >
            <LogOut className="h-3.5 w-3.5" strokeWidth={2.2} />
            Déconnexion
          </button>
        </div>

          <div className="mt-6 pb-16 sm:mt-8">
            {tab === 'commandes' && <OrdersTab />}
            {tab === 'informations' && <InformationsTab />}
            {tab === 'favoris' && <FavoritesTab />}
          </div>
        </div>
      </div>

      <Footer />
    </div>
  );
}

/* ------------------------------------------------------------------ bandeau */

function VerificationBanner() {
  const { resendVerification } = useAuth();
  const [state, setState] = useState<'idle' | 'sending' | 'sent' | 'error'>('idle');
  const [message, setMessage] = useState('');

  return (
    <div className="mt-7 flex flex-col gap-3 border border-[#E2B04A]/35 bg-[#E2B04A]/[0.07] px-5 py-4 sm:flex-row sm:items-center sm:gap-5">
      <AlertTriangle className="h-4 w-4 shrink-0 text-[#E2B04A]" strokeWidth={2.2} />

      <div className="min-w-0 flex-1">
        <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-[#E2B04A]">
          Adresse e-mail non vérifiée
        </p>
        {/* Le risque concret, pas une injonction vague : c'est ce qui décide le
            client à cliquer. Sans adresse vérifiée, aucun lien de
            réinitialisation ne peut lui parvenir. */}
        <p className="mt-1.5 text-[11px] leading-[1.7] text-white/60">
          Vous pouvez commander normalement. Mais tant que votre adresse n'est pas confirmée, nous ne pouvons pas
          vous envoyer de lien en cas de mot de passe oublié — et vos confirmations de commande risquent de ne
          jamais arriver.
        </p>
        {message && (
          <p className={`mt-2 text-[11px] ${state === 'error' ? 'text-[#E2564A]' : 'text-white/75'}`}>{message}</p>
        )}
      </div>

      <button
        type="button"
        disabled={state === 'sending' || state === 'sent'}
        onClick={() => {
          setState('sending');
          resendVerification()
            .then((text) => {
              setState('sent');
              setMessage(text);
            })
            .catch((error: unknown) => {
              setState('error');
              setMessage(
                error instanceof ApiFailure ? error.message : 'Envoi impossible. Réessayez dans un instant.',
              );
            });
        }}
        className="shrink-0 border border-[#E2B04A]/50 px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.12em] text-[#E2B04A] transition-colors hover:bg-[#E2B04A] hover:text-[#17191C] disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:bg-transparent disabled:hover:text-[#E2B04A]"
      >
        {state === 'sending' ? 'Envoi…' : state === 'sent' ? 'Envoyé' : 'Renvoyer le lien'}
      </button>
    </div>
  );
}

/* ----------------------------------------------------------------- commandes */

type OrderItem = {
  item_type: string;
  item_id: number;
  title: string;
  subtitle: string;
  size: string;
  unit_price_xof: number;
  qty: number;
  line_total_xof: number;
};

type Order = {
  reference: string;
  status: string;
  payment_method: string;
  delivery_label: string;
  delivery_address: string;
  subtotal_xof: number;
  delivery_fee_xof: number;
  total_xof: number;
  created_at: string;
  items: OrderItem[];
};

const STATUSES: Record<string, { label: string; className: string }> = {
  pending: { label: 'En préparation', className: 'border-[#E2B04A]/40 bg-[#E2B04A]/10 text-[#E2B04A]' },
  confirmed: { label: 'Confirmée', className: 'border-[#6BA8E2]/40 bg-[#6BA8E2]/10 text-[#9CC6EE]' },
  shipped: { label: 'Expédiée', className: 'border-[#6BA8E2]/40 bg-[#6BA8E2]/10 text-[#9CC6EE]' },
  delivered: { label: 'Livrée', className: 'border-[#4A9E6B]/40 bg-[#4A9E6B]/10 text-[#7FCB9B]' },
  cancelled: { label: 'Annulée', className: 'border-[#E2564A]/40 bg-[#E2564A]/10 text-[#F2A79E]' },
};

/** Les dates arrivent en UTC ; Cotonou est à UTC+1. */
const formatDate = (utc: string) =>
  new Date(utc.replace(' ', 'T') + 'Z').toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });

function OrdersTab() {
  const [orders, setOrders] = useState<Order[] | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;

    api<{ orders: Order[] }>('/orders')
      .then((data) => {
        if (!cancelled) setOrders(data.orders);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setOrders([]);
        setError(err instanceof ApiFailure ? err.message : 'Impossible de charger vos commandes.');
      });

    return () => {
      cancelled = true;
    };
  }, []);

  if (orders === null) {
    return <p className="text-[11px] uppercase tracking-[0.18em] text-white/40">Chargement…</p>;
  }

  if (orders.length === 0) {
    return (
      <div className={cardClass}>
        <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-[#EDEFF2]">Aucune commande</p>
        <p className="mt-2 max-w-lg text-[11px] leading-[1.7] text-white/55">
          {error || 'Vos commandes apparaîtront ici dès que vous en aurez passé une, avec leur statut de livraison et leur facture.'}
        </p>
        <Link
          to="/boutique"
          className="mt-5 inline-block border border-white px-6 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-white transition-colors hover:bg-white hover:text-[#141516]"
        >
          Voir la boutique
        </Link>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      {orders.map((order) => {
        const statut = STATUSES[order.status] ?? STATUSES.pending;
        const articles = order.items.reduce((n, item) => n + item.qty, 0);

        return (
          <article key={order.reference} className={cardClass}>
            <header className="flex flex-wrap items-start justify-between gap-4">
              <div className="min-w-0">
                <p className="font-display text-[15px] uppercase tracking-[0.06em] text-[#EDEFF2]">
                  {order.reference}
                </p>
                <p className="mt-1 text-[11px] text-white/45">
                  {formatDate(order.created_at)} — {articles} article{articles > 1 ? 's' : ''}
                </p>
              </div>

              <span
                className={`shrink-0 border px-3 py-1.5 text-[9px] font-bold uppercase tracking-[0.14em] ${statut.className}`}
              >
                {statut.label}
              </span>
            </header>

            <ul className="mt-5 flex flex-col gap-3 border-t border-white/10 pt-4">
              {order.items.map((item, index) => (
                <li key={`${item.item_type}-${item.item_id}-${item.size}-${index}`} className="flex gap-4">
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-[12px] font-bold uppercase tracking-[0.06em] text-[#EDEFF2]">
                      {item.title}
                    </p>
                    <p className="mt-0.5 truncate text-[10px] text-white/45">
                      {item.subtitle} — taille {item.size} × {item.qty}
                    </p>
                  </div>
                  <span className="shrink-0 font-display text-[13px] text-[#EDEFF2]">
                    {formatXof(item.line_total_xof)}
                  </span>
                </li>
              ))}
            </ul>

            <dl className="mt-4 flex flex-col gap-1.5 border-t border-white/10 pt-4 text-[11px]">
              <div className="flex justify-between text-white/45">
                <dt>Sous-total</dt>
                <dd>{formatXof(order.subtotal_xof)}</dd>
              </div>
              <div className="flex justify-between text-white/45">
                <dt>Livraison — {order.delivery_label}</dt>
                <dd>{formatXof(order.delivery_fee_xof)}</dd>
              </div>
              <div className="mt-1 flex items-baseline justify-between border-t border-white/10 pt-2.5">
                <dt className="font-bold uppercase tracking-[0.14em] text-[#EDEFF2]">Total</dt>
                <dd className="font-display text-[17px] text-[#EDEFF2]">{formatXof(order.total_xof)}</dd>
              </div>
            </dl>

            <p className="mt-4 text-[10px] leading-[1.6] text-white/35">
              Livraison à : {order.delivery_address}
            </p>

            {/* Un lien, pas un appel en JavaScript : le navigateur envoie le
                cookie de session comme pour n'importe quelle navigation, et
                enregistre le fichier lui-même. Rien à assembler en mémoire. */}
            <a
              href={`/api/orders/${order.reference}/facture`}
              download={`facture-${order.reference.toLowerCase()}.pdf`}
              className="mt-5 flex w-fit items-center gap-2 border border-white/20 px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.12em] text-white/70 transition-colors hover:border-white hover:text-white"
            >
              <Download className="h-3.5 w-3.5" strokeWidth={2.2} />
              Télécharger la facture
            </a>
          </article>
        );
      })}
    </div>
  );
}

/* -------------------------------------------------------------- informations */

function InformationsTab() {
  const { customer, updateProfile, updatePassword } = useAuth();

  const [profile, setProfile] = useState({
    name: customer?.name ?? '',
    email: customer?.email ?? '',
    phone: customer?.phone ?? '',
  });
  const [profileErrors, setProfileErrors] = useState<Record<string, string>>({});
  const [profileState, setProfileState] = useState<'idle' | 'saving' | 'saved'>('idle');
  const [profileNotice, setProfileNotice] = useState('');

  const [passwords, setPasswords] = useState({ current_password: '', password: '' });
  const [passwordErrors, setPasswordErrors] = useState<Record<string, string>>({});
  const [passwordState, setPasswordState] = useState<'idle' | 'saving' | 'saved'>('idle');

  return (
    <div className="grid gap-5 lg:grid-cols-2">
      {/* ---------- coordonnées ---------- */}
      <form
        className={cardClass}
        onSubmit={(event) => {
          event.preventDefault();
          setProfileErrors({});
          setProfileNotice('');
          setProfileState('saving');

          const emailChanged = profile.email !== customer?.email;

          updateProfile(profile)
            .then(() => {
              setProfileState('saved');
              setProfileNotice(
                emailChanged
                  ? 'Coordonnées enregistrées. Un lien de vérification vient de partir vers votre nouvelle adresse.'
                  : 'Coordonnées enregistrées.',
              );
            })
            .catch((error: unknown) => {
              setProfileState('idle');
              if (error instanceof ApiFailure) {
                setProfileErrors(error.fields);
                if (Object.keys(error.fields).length === 0) setProfileNotice(error.message);
              }
            });
        }}
      >
        <h2 className="font-display text-[13px] uppercase tracking-[0.08em] text-[#EDEFF2]">Mes coordonnées</h2>

        <div className="mt-5 flex flex-col gap-4">
          <Field
            label="Nom complet"
            error={profileErrors.name}
            value={profile.name}
            onChange={(value) => setProfile((p) => ({ ...p, name: value }))}
            autoComplete="name"
          />
          <Field
            label="E-mail"
            type="email"
            error={profileErrors.email}
            value={profile.email}
            onChange={(value) => setProfile((p) => ({ ...p, email: value }))}
            autoComplete="email"
            hint={
              profile.email !== customer?.email
                ? 'Changer d’adresse annule la vérification : un nouveau lien partira.'
                : undefined
            }
          />
          <Field
            label="Téléphone"
            type="tel"
            error={profileErrors.phone}
            value={profile.phone}
            onChange={(value) => setProfile((p) => ({ ...p, phone: value }))}
            autoComplete="tel"
          />
        </div>

        {profileNotice && (
          <p className={`mt-4 text-[11px] leading-[1.6] ${profileState === 'saved' ? 'text-[#7FCB9B]' : 'text-[#E2564A]'}`}>
            {profileNotice}
          </p>
        )}

        <button
          type="submit"
          disabled={profileState === 'saving'}
          className="mt-5 w-full bg-white px-6 py-3.5 text-[11px] font-bold uppercase tracking-[0.12em] text-[#141516] transition-opacity hover:opacity-90 disabled:opacity-50"
        >
          {profileState === 'saving' ? 'Enregistrement…' : 'Enregistrer'}
        </button>
      </form>

      {/* ---------- mot de passe ---------- */}
      <form
        className={cardClass}
        onSubmit={(event) => {
          event.preventDefault();
          setPasswordErrors({});
          setPasswordState('saving');

          updatePassword(passwords.current_password, passwords.password)
            .then(() => {
              setPasswordState('saved');
              setPasswords({ current_password: '', password: '' });
            })
            .catch((error: unknown) => {
              setPasswordState('idle');
              if (error instanceof ApiFailure) {
                setPasswordErrors(
                  Object.keys(error.fields).length > 0 ? error.fields : { password: error.message },
                );
              }
            });
        }}
      >
        <h2 className="font-display text-[13px] uppercase tracking-[0.08em] text-[#EDEFF2]">Mot de passe</h2>
        <p className="mt-2 text-[11px] leading-[1.7] text-white/50">
          Changer votre mot de passe déconnecte vos autres appareils. Celui-ci reste connecté.
        </p>

        <div className="mt-5 flex flex-col gap-4">
          <Field
            label="Mot de passe actuel"
            type="password"
            error={passwordErrors.current_password}
            value={passwords.current_password}
            onChange={(value) => setPasswords((p) => ({ ...p, current_password: value }))}
            autoComplete="current-password"
          />
          <Field
            label="Nouveau mot de passe"
            type="password"
            error={passwordErrors.password}
            value={passwords.password}
            onChange={(value) => setPasswords((p) => ({ ...p, password: value }))}
            autoComplete="new-password"
            placeholder="8 caractères minimum"
          />
        </div>

        {passwordState === 'saved' && (
          <p className="mt-4 flex items-center gap-2 text-[11px] text-[#7FCB9B]">
            <Check className="h-3.5 w-3.5" strokeWidth={2.6} />
            Mot de passe modifié.
          </p>
        )}

        <button
          type="submit"
          disabled={passwordState === 'saving'}
          className="mt-5 w-full border border-white px-6 py-3.5 text-[11px] font-bold uppercase tracking-[0.12em] text-white transition-colors hover:bg-white hover:text-[#141516] disabled:opacity-50"
        >
          {passwordState === 'saving' ? 'Modification…' : 'Modifier le mot de passe'}
        </button>
      </form>
    </div>
  );
}

type FieldProps = {
  label: string;
  value: string;
  onChange: (value: string) => void;
  type?: string;
  error?: string;
  hint?: string;
  placeholder?: string;
  autoComplete?: string;
};

function Field({ label, value, onChange, type = 'text', error, hint, placeholder, autoComplete }: FieldProps) {
  return (
    <label className="flex flex-col gap-2">
      <span className={labelClass}>{label}</span>
      <input
        type={type}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        autoComplete={autoComplete}
        aria-invalid={error ? true : undefined}
        className={`${inputClass} ${error ? 'border-[#E2564A]/70' : ''}`}
      />
      {error && <span className="text-[10px] leading-[1.5] text-[#E2564A]">{error}</span>}
      {!error && hint && <span className="text-[10px] leading-[1.5] text-white/40">{hint}</span>}
    </label>
  );
}

/* ------------------------------------------------------------------ favoris */

function FavoritesTab() {
  const { favorites, loading } = useFavorites();
  const { products, jerseys, loading: catalogueEnCours } = useCatalogue();

  const items = useMemo(
    () =>
      favorites
        .map((favorite) =>
          favorite.item_type === 'sneaker'
            ? { kind: 'sneaker' as const, data: products.find((p) => p.id === favorite.item_id) }
            : { kind: 'jersey' as const, data: jerseys.find((j) => j.id === favorite.item_id) },
        )
        // Un favori dont l'article a disparu du catalogue ne doit pas laisser
        // une carte vide dans la grille.
        .filter((item) => item.data !== undefined),
    [favorites, products, jerseys],
  );

  if (loading || catalogueEnCours) {
    return <p className="text-[11px] uppercase tracking-[0.18em] text-white/40">Chargement…</p>;
  }

  if (items.length === 0) {
    return (
      <div className={cardClass}>
        <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-[#EDEFF2]">Aucun favori</p>
        <p className="mt-2 max-w-lg text-[11px] leading-[1.7] text-white/55">
          Touchez le cœur sur une paire ou un maillot pour le mettre de côté. Vos favoris vous suivent d'un appareil
          à l'autre.
        </p>
        <Link
          to="/boutique"
          className="mt-5 inline-block border border-white px-6 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-white transition-colors hover:bg-white hover:text-[#141516]"
        >
          Parcourir la boutique
        </Link>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
      {items.map((item) =>
        item.kind === 'sneaker' ? (
          <ProductCard key={`sneaker-${item.data!.id}`} product={item.data!} />
        ) : (
          <JerseyCard key={`jersey-${item.data!.id}`} jersey={item.data!} />
        ),
      )}
    </div>
  );
}
