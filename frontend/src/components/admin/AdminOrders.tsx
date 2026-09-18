import { useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { ChevronLeft, ChevronRight, Download, Phone, Search, X } from 'lucide-react';
import { ApiFailure, api } from '../../api/client';
import { formatXof } from '../../utils/format';
import {
  Badge,
  Button,
  EmptyState,
  ErrorBanner,
  Loading,
  PageTitle,
  cardClass,
  formatDate,
  inputClass,
  type Tone,
} from './ui';

type OrderRow = {
  reference: string;
  status: string;
  status_label: string;
  payment_method: string;
  contact_name: string;
  contact_phone: string;
  delivery_label: string;
  total_xof: number;
  items_count: number;
  created_at: string;
};

type OrderItem = {
  title: string;
  subtitle: string;
  size: string;
  qty: number;
  unit_price_xof: number;
  line_total_xof: number;
};

type OrderDetail = OrderRow & {
  contact_email: string;
  delivery_address: string;
  subtotal_xof: number;
  delivery_fee_xof: number;
  payment_status: string;
  next_statuses: { id: string; label: string }[];
  items: OrderItem[];
};

const TONE_BY_STATUS: Record<string, Tone> = {
  pending: 'amber',
  confirmed: 'blue',
  shipped: 'blue',
  delivered: 'green',
  cancelled: 'red',
};

const ONGLETS = [
  { id: '', label: 'Toutes' },
  { id: 'pending', label: 'En préparation' },
  { id: 'confirmed', label: 'Confirmées' },
  { id: 'shipped', label: 'Expédiées' },
  { id: 'delivered', label: 'Livrées' },
  { id: 'cancelled', label: 'Annulées' },
] as const;

export default function AdminOrders() {
  const [params, setParams] = useSearchParams();
  const statut = params.get('statut') ?? '';

  const [search, setSearch] = useState(params.get('q') ?? '');
  const [page, setPage] = useState(1);
  const [data, setData] = useState<{ orders: OrderRow[]; pagination: { pages: number; total: number } } | null>(null);
  const [ouverte, setOuverte] = useState<OrderDetail | null>(null);
  const [erreur, setErreur] = useState('');

  const charger = useCallback(() => {
    const query = new URLSearchParams();
    if (statut) query.set('status', statut);
    if (search.trim()) query.set('search', search.trim());
    query.set('page', String(page));

    api<{ orders: OrderRow[]; pagination: { pages: number; total: number } }>(`/admin/orders?${query}`)
      .then(setData)
      .catch(() => setErreur('Impossible de charger les commandes.'));
  }, [statut, search, page]);

  useEffect(() => {
    charger();
  }, [charger]);

  // La recherche est différée : interroger le serveur à chaque frappe le
  // sollicite pour rien et fait clignoter la liste sous les doigts.
  useEffect(() => {
    const id = window.setTimeout(() => setPage(1), 350);

    return () => window.clearTimeout(id);
  }, [search]);

  const ouvrir = (reference: string) => {
    setErreur('');
    api<{ order: OrderDetail }>(`/admin/orders/${reference}`)
      .then((d) => setOuverte(d.order))
      .catch(() => setErreur('Impossible de charger cette commande.'));
  };

  const changerStatut = (reference: string, status: string) => {
    api<{ status: string; status_label: string; next_statuses: { id: string; label: string }[] }>(
      `/admin/orders/${reference}/status`,
      { method: 'POST', body: { status } },
    )
      .then((d) => {
        setOuverte((current) =>
          current === null ? null : { ...current, status: d.status, status_label: d.status_label, next_statuses: d.next_statuses },
        );
        charger();
      })
      .catch((error: unknown) => {
        setErreur(error instanceof ApiFailure ? (error.fields.status ?? error.message) : 'Changement refusé.');
      });
  };

  return (
    <>
      <PageTitle
        title="Commandes"
        lead="Le client est prévenu par e-mail à chaque changement de statut."
        action={
          <div className="relative w-full sm:w-[280px]">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-white/30" />
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Référence, nom, téléphone…"
              className={`${inputClass} pl-9`}
            />
          </div>
        }
      />

      <ErrorBanner message={erreur} />

      <div className="mb-5 flex flex-wrap gap-2">
        {ONGLETS.map((onglet) => (
          <button
            key={onglet.id}
            type="button"
            onClick={() => {
              setPage(1);
              setParams(onglet.id ? { statut: onglet.id } : {}, { replace: true });
            }}
            className={`border px-3.5 py-2 text-[10px] font-bold uppercase tracking-[0.12em] transition-colors ${
              statut === onglet.id
                ? 'border-[#EDEFF2] bg-[#EDEFF2] text-[#17191C]'
                : 'border-white/15 text-white/50 hover:border-white/40 hover:text-white'
            }`}
          >
            {onglet.label}
          </button>
        ))}
      </div>

      {data === null ? (
        <Loading />
      ) : data.orders.length === 0 ? (
        <EmptyState title={search ? 'Aucun résultat' : 'Aucune commande'}>
          {search
            ? `Rien ne correspond à « ${search} ».`
            : "Les commandes passées sur le site apparaîtront ici, avec le détail des articles et les coordonnées du client."}
        </EmptyState>
      ) : (
        <>
          <div className={`${cardClass} overflow-x-auto`}>
            <table className="w-full min-w-[720px] border-collapse text-left">
              <thead>
                <tr className="border-b border-white/10 text-[9px] uppercase tracking-[0.16em] text-white/35">
                  <th className="px-4 py-3 font-bold">Référence</th>
                  <th className="px-4 py-3 font-bold">Client</th>
                  <th className="px-4 py-3 font-bold">Livraison</th>
                  <th className="px-4 py-3 font-bold">Date</th>
                  <th className="px-4 py-3 text-right font-bold">Total</th>
                  <th className="px-4 py-3 font-bold">Statut</th>
                </tr>
              </thead>
              <tbody>
                {data.orders.map((order) => (
                  <tr
                    key={order.reference}
                    onClick={() => ouvrir(order.reference)}
                    className="cursor-pointer border-b border-white/[0.06] transition-colors last:border-0 hover:bg-white/[0.04]"
                  >
                    <td className="px-4 py-3.5">
                      <span className="font-display text-[12px] tracking-[0.04em] text-[#EDEFF2]">
                        {order.reference}
                      </span>
                      <span className="mt-0.5 block text-[10px] text-white/35">
                        {order.items_count} article{order.items_count > 1 ? 's' : ''}
                      </span>
                    </td>
                    <td className="px-4 py-3.5">
                      <span className="block truncate text-[11px] text-white/80">{order.contact_name}</span>
                      <span className="mt-0.5 block text-[10px] text-white/35">{order.contact_phone}</span>
                    </td>
                    <td className="px-4 py-3.5 text-[11px] text-white/55">{order.delivery_label}</td>
                    <td className="px-4 py-3.5 text-[11px] text-white/45">{formatDate(order.created_at)}</td>
                    <td className="px-4 py-3.5 text-right font-display text-[13px] text-[#EDEFF2]">
                      {formatXof(order.total_xof)}
                    </td>
                    <td className="px-4 py-3.5">
                      <Badge tone={TONE_BY_STATUS[order.status] ?? 'neutral'}>{order.status_label}</Badge>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {data.pagination.pages > 1 && (
            <div className="mt-4 flex items-center justify-between">
              <p className="text-[10px] uppercase tracking-[0.12em] text-white/35">
                Page {page} sur {data.pagination.pages} — {data.pagination.total} commandes
              </p>
              <div className="flex gap-2">
                <Button variant="ghost" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                  <ChevronLeft className="h-3.5 w-3.5" strokeWidth={2.2} />
                </Button>
                <Button
                  variant="ghost"
                  disabled={page >= data.pagination.pages}
                  onClick={() => setPage((p) => p + 1)}
                >
                  <ChevronRight className="h-3.5 w-3.5" strokeWidth={2.2} />
                </Button>
              </div>
            </div>
          )}
        </>
      )}

      {ouverte && <OrderPanel order={ouverte} onClose={() => setOuverte(null)} onStatus={changerStatut} />}
    </>
  );
}

function OrderPanel({
  order,
  onClose,
  onStatus,
}: {
  order: OrderDetail;
  onClose: () => void;
  onStatus: (reference: string, status: string) => void;
}) {
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };

    window.addEventListener('keydown', onKey);

    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-[70] flex justify-end" role="dialog" aria-modal="true">
      <div onClick={onClose} className="absolute inset-0 bg-black/65 backdrop-blur-sm" />

      <aside className="relative flex h-full w-full max-w-md flex-col overflow-y-auto border-l border-white/10 bg-[#0B0C0E]">
        <header className="flex items-start justify-between gap-4 border-b border-white/10 px-6 py-5">
          <div className="min-w-0">
            <p className="font-display text-[15px] uppercase tracking-[0.05em] text-[#EDEFF2]">{order.reference}</p>
            <p className="mt-1 text-[10px] text-white/40">{formatDate(order.created_at, true)}</p>
          </div>

          <div className="flex shrink-0 items-center gap-3">
            {/* Le gérant a besoin de la facture pour répondre au téléphone à
                quelqu'un qui n'a pas retrouvé la sienne. */}
            <a
              href={`/api/orders/${order.reference}/facture`}
              download={`facture-${order.reference.toLowerCase()}.pdf`}
              title="Télécharger la facture"
              className="flex items-center gap-1.5 border border-white/20 px-2.5 py-1.5 text-[9px] font-bold uppercase tracking-[0.12em] text-white/70 transition-colors hover:border-white hover:text-white"
            >
              <Download className="h-3 w-3" strokeWidth={2.2} />
              Facture
            </a>
            <button type="button" onClick={onClose} aria-label="Fermer" className="text-white/40 hover:text-white">
              <X className="h-5 w-5" strokeWidth={2} />
            </button>
          </div>
        </header>

        <div className="px-6 py-5">
          <Badge tone={TONE_BY_STATUS[order.status] ?? 'neutral'}>{order.status_label}</Badge>

          {order.next_statuses.length > 0 ? (
            <div className="mt-4">
              <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-white/45">Faire avancer</p>
              <div className="mt-2.5 flex flex-wrap gap-2">
                {order.next_statuses.map((suite) => (
                  <Button
                    key={suite.id}
                    variant={suite.id === 'cancelled' ? 'danger' : 'primary'}
                    onClick={() => onStatus(order.reference, suite.id)}
                  >
                    {suite.label}
                  </Button>
                ))}
              </div>
              <p className="mt-2.5 text-[10px] leading-[1.6] text-white/30">
                Le client reçoit un e-mail à chaque changement.
                {order.next_statuses.some((s) => s.id === 'cancelled') &&
                  ' Une annulation remet les articles en vente.'}
              </p>
            </div>
          ) : (
            <p className="mt-4 text-[10px] leading-[1.6] text-white/30">
              Cette commande est close : plus aucun changement de statut n'est possible.
            </p>
          )}

          <section className="mt-7">
            <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-white/45">Client</p>
            <p className="mt-2 text-[12px] text-[#EDEFF2]">{order.contact_name}</p>
            <a
              href={`tel:${order.contact_phone.replace(/\s/g, '')}`}
              className="mt-1.5 inline-flex items-center gap-2 text-[12px] text-white/70 transition-colors hover:text-white"
            >
              <Phone className="h-3.5 w-3.5" strokeWidth={2.2} />
              {order.contact_phone}
            </a>
            <a
              href={`mailto:${order.contact_email}`}
              className="mt-1 block truncate text-[11px] text-white/45 transition-colors hover:text-white"
            >
              {order.contact_email}
            </a>
          </section>

          <section className="mt-6">
            <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-white/45">Livraison</p>
            <p className="mt-2 text-[11px] text-white/70">{order.delivery_label}</p>
            <p className="mt-1 text-[11px] leading-[1.6] text-white/45">{order.delivery_address}</p>
          </section>

          <section className="mt-6">
            <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-white/45">Articles</p>
            <ul className="mt-3 flex flex-col gap-3 border-t border-white/10 pt-3">
              {order.items.map((item, index) => (
                <li key={index} className="flex gap-3">
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-[11px] font-bold uppercase tracking-[0.05em] text-[#EDEFF2]">
                      {item.title}
                    </p>
                    <p className="mt-0.5 truncate text-[10px] text-white/40">
                      {item.subtitle} — taille {item.size} × {item.qty}
                    </p>
                  </div>
                  <span className="shrink-0 font-display text-[12px] text-[#EDEFF2]">
                    {formatXof(item.line_total_xof)}
                  </span>
                </li>
              ))}
            </ul>

            <dl className="mt-4 flex flex-col gap-1.5 border-t border-white/10 pt-3 text-[11px]">
              <div className="flex justify-between text-white/40">
                <dt>Sous-total</dt>
                <dd>{formatXof(order.subtotal_xof)}</dd>
              </div>
              <div className="flex justify-between text-white/40">
                <dt>Livraison</dt>
                <dd>{formatXof(order.delivery_fee_xof)}</dd>
              </div>
              <div className="mt-1 flex items-baseline justify-between border-t border-white/10 pt-2.5">
                <dt className="font-bold uppercase tracking-[0.12em] text-[#EDEFF2]">Total</dt>
                <dd className="font-display text-[16px] text-[#EDEFF2]">{formatXof(order.total_xof)}</dd>
              </div>
            </dl>

            <p className="mt-3 text-[10px] text-white/35">
              {order.payment_method === 'cash' ? 'Espèces à la livraison' : order.payment_method} —{' '}
              {order.payment_status === 'paid' ? 'réglée' : 'non réglée'}
            </p>
          </section>
        </div>
      </aside>
    </div>
  );
}
