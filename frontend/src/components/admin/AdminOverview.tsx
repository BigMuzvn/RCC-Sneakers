import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { AlertTriangle, ImageOff, Mail, Package, PackageX, Users } from 'lucide-react';
import { api } from '../../api/client';
import { formatXof } from '../../utils/format';
import { cardClass, EmptyState, formatDate, Loading, PageTitle } from './ui';

type Overview = {
  orders: {
    to_handle: number;
    by_status: Record<string, number>;
    last_24h: number;
    revenue_30d: number;
  };
  catalogue: {
    products: number;
    jerseys: number;
    without_image: number;
    out_of_stock_sizes: number;
    units_in_stock: number;
  };
  customers: { total: number; unverified: number; last_30d: number };
  inbox: { new_messages: number; subscribers: number; unsynced: number };
};

type LogEntry = {
  admin_email: string;
  action: string;
  target: string;
  detail: string | null;
  created_at: string;
};

export default function AdminOverview() {
  const [data, setData] = useState<Overview | null>(null);
  const [log, setLog] = useState<LogEntry[]>([]);

  useEffect(() => {
    void api<Overview>('/admin/overview').then(setData).catch(() => undefined);
    void api<{ entries: LogEntry[] }>('/admin/log?limit=12')
      .then((d) => setLog(d.entries))
      .catch(() => undefined);
  }, []);

  if (!data) return <Loading />;

  return (
    <>
      <PageTitle
        title="Vue d'ensemble"
        lead="Ce qui demande une action aujourd'hui, et l'état du catalogue."
      />

      {/* Ce qui appelle un geste passe en premier : une commande en attente vaut
          plus d'attention qu'un chiffre d'affaires. */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <Stat
          to="/admin/commandes?statut=pending"
          icon={Package}
          value={data.orders.to_handle}
          label="Commandes à traiter"
          hint={`${data.orders.last_24h} depuis 24 h`}
          urgent={data.orders.to_handle > 0}
        />
        <Stat
          to="/admin/messages"
          icon={Mail}
          value={data.inbox.new_messages}
          label="Messages non lus"
          hint={`${data.inbox.subscribers} inscrits à la lettre`}
          urgent={data.inbox.new_messages > 0}
        />
        <Stat
          to="/admin/produits"
          icon={PackageX}
          value={data.catalogue.out_of_stock_sizes}
          label="Tailles épuisées"
          hint={`${data.catalogue.units_in_stock} pièces en stock`}
          urgent={data.catalogue.out_of_stock_sizes > 0}
        />
        <Stat
          to="/admin/clients"
          icon={Users}
          value={data.customers.total}
          label="Clients"
          hint={`${data.customers.last_30d} ce mois-ci`}
        />
      </div>

      <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className={`${cardClass} p-5 sm:p-6`}>
          <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-white/45">Ventes sur 30 jours</p>
          <p className="mt-2 font-display text-[clamp(22px,4vw,30px)] text-[#EDEFF2]">
            {formatXof(data.orders.revenue_30d)}
          </p>
          <p className="mt-1 text-[10px] text-white/35">Commandes annulées exclues.</p>

          <ul className="mt-5 flex flex-col gap-2 border-t border-white/10 pt-4">
            {Object.entries(STATUS_LABELS).map(([id, label]) => (
              <li key={id} className="flex items-baseline justify-between text-[11px]">
                <span className="text-white/45">{label}</span>
                <span className="font-display text-[13px] text-[#EDEFF2]">{data.orders.by_status[id] ?? 0}</span>
              </li>
            ))}
          </ul>
        </div>

        <div className={`${cardClass} p-5 sm:p-6`}>
          <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-white/45">Catalogue</p>

          <ul className="mt-4 flex flex-col gap-3 text-[11px]">
            <Line label="Sneakers en vente" value={data.catalogue.products} to="/admin/produits" />
            <Line label="Maillots en vente" value={data.catalogue.jerseys} to="/admin/maillots" />
          </ul>

          {data.catalogue.without_image > 0 && (
            <p className="mt-5 flex items-start gap-2 border-t border-white/10 pt-4 text-[11px] leading-[1.6] text-[#E2B04A]">
              <ImageOff className="mt-0.5 h-3.5 w-3.5 shrink-0" strokeWidth={2.2} />
              <span>
                {data.catalogue.without_image} article{data.catalogue.without_image > 1 ? 's' : ''} sans visuel. Les
                cartes affichent un halo à la place — c'est assumé, mais une photo vend mieux.
              </span>
            </p>
          )}

          {data.customers.unverified > 0 && (
            <p className="mt-3 flex items-start gap-2 text-[11px] leading-[1.6] text-white/40">
              <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" strokeWidth={2.2} />
              <span>
                {data.customers.unverified} client{data.customers.unverified > 1 ? 's' : ''} n'ont pas confirmé leur
                adresse. Ils ne pourront pas réinitialiser leur mot de passe.
              </span>
            </p>
          )}
        </div>

        <div className={`${cardClass} p-5 sm:p-6`}>
          <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-white/45">Dernières actions</p>

          {log.length === 0 ? (
            <p className="mt-4 text-[11px] text-white/35">Rien encore.</p>
          ) : (
            <ul className="mt-4 flex flex-col gap-3">
              {log.map((entry, index) => (
                <li key={index} className="border-l border-white/10 pl-3 text-[11px] leading-[1.5]">
                  <p className="text-white/70">
                    <span className="text-[#EDEFF2]">{ACTION_LABELS[entry.action] ?? entry.action}</span>{' '}
                    {entry.target}
                  </p>
                  {entry.detail && <p className="mt-0.5 truncate text-white/35">{entry.detail}</p>}
                  <p className="mt-0.5 text-[10px] text-white/25">
                    {formatDate(entry.created_at, true)} — {entry.admin_email}
                  </p>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>

      {data.inbox.unsynced > 0 && (
        <div className="mt-4">
          <EmptyState title={`${data.inbox.unsynced} inscrit(s) non synchronisés`}>
            Ces adresses ne sont jamais arrivées chez Brevo — un envoi a dû échouer. Elles sont conservées ici et
            peuvent être rejouées depuis la{' '}
            <Link to="/admin/messages?onglet=newsletter" className="underline underline-offset-2">
              messagerie
            </Link>
            .
          </EmptyState>
        </div>
      )}
    </>
  );
}

const STATUS_LABELS: Record<string, string> = {
  pending: 'En préparation',
  confirmed: 'Confirmées',
  shipped: 'Expédiées',
  delivered: 'Livrées',
  cancelled: 'Annulées',
};

const ACTION_LABELS: Record<string, string> = {
  'order.status': 'Statut de commande',
  'product.create': 'Sneaker créée',
  'product.update': 'Sneaker modifiée',
  'product.stock': 'Stock sneaker',
  'jersey.create': 'Maillot créé',
  'jersey.update': 'Maillot modifié',
  'jersey.stock': 'Stock maillot',
  'image.upload': 'Visuel ajouté',
  'customer.status': 'Statut client',
  'customer.anonymise': 'Client anonymisé',
  'customer.role': 'Rôle client',
  'message.status': 'Message',
  'newsletter.unsubscribe': 'Désinscription',
  'newsletter.sync': 'Synchronisation',
  'settings.update': 'Réglages',
  'featured.update': 'Vitrine',
  'zone.update': 'Zone de livraison',
};

function Stat({
  to,
  icon: Icon,
  value,
  label,
  hint,
  urgent = false,
}: {
  to: string;
  icon: typeof Package;
  value: number;
  label: string;
  hint: string;
  urgent?: boolean;
}) {
  return (
    <Link
      to={to}
      className={`${cardClass} group flex flex-col p-5 transition-colors hover:border-white/30 ${
        urgent ? 'border-[#E2B04A]/35' : ''
      }`}
    >
      <Icon className={`h-4 w-4 ${urgent ? 'text-[#E2B04A]' : 'text-white/35'}`} strokeWidth={2.1} />
      <p className="mt-3 font-display text-[clamp(24px,4vw,32px)] leading-none text-[#EDEFF2]">{value}</p>
      <p className="mt-2 text-[10px] font-bold uppercase tracking-[0.14em] text-white/55">{label}</p>
      <p className="mt-1 text-[10px] text-white/30">{hint}</p>
    </Link>
  );
}

function Line({ label, value, to }: { label: string; value: number; to: string }) {
  return (
    <li className="flex items-baseline justify-between">
      <Link to={to} className="text-white/45 transition-colors hover:text-white">
        {label}
      </Link>
      <span className="font-display text-[13px] text-[#EDEFF2]">{value}</span>
    </li>
  );
}
