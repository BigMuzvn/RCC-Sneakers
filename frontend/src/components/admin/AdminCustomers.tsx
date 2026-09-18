import { useCallback, useEffect, useState } from 'react';
import { Search, ShieldAlert, X } from 'lucide-react';
import { ApiFailure, api } from '../../api/client';
import { formatXof } from '../../utils/format';
import {
  Badge,
  Button,
  EmptyState,
  ErrorBanner,
  Loading,
  PageTitle,
  SuccessBanner,
  cardClass,
  formatDate,
  inputClass,
} from './ui';

type CustomerRow = {
  id: number;
  name: string;
  email: string;
  phone: string;
  status: string;
  email_verified: boolean;
  orders_count: number;
  spent_xof: number;
  created_at: string;
};

type CustomerDetail = CustomerRow & {
  orders: { reference: string; status: string; total_xof: number; created_at: string }[];
  favorites: { item_type: string; item_id: number }[];
};

const STATUS_LABEL: Record<string, string> = {
  active: 'Actif',
  suspended: 'Suspendu',
  anonymised: 'Anonymisé',
};

export default function AdminCustomers() {
  const [search, setSearch] = useState('');
  const [rows, setRows] = useState<CustomerRow[] | null>(null);
  const [ouvert, setOuvert] = useState<CustomerDetail | null>(null);
  const [erreur, setErreur] = useState('');
  const [succes, setSucces] = useState('');

  const charger = useCallback(() => {
    const query = new URLSearchParams();
    if (search.trim()) query.set('search', search.trim());

    api<{ customers: CustomerRow[] }>(`/admin/customers?${query}`)
      .then((d) => setRows(d.customers))
      .catch(() => setErreur('Impossible de charger les clients.'));
  }, [search]);

  useEffect(() => {
    const id = window.setTimeout(charger, 300);

    return () => window.clearTimeout(id);
  }, [charger]);

  const ouvrir = (id: number) => {
    api<{ customer: CustomerDetail }>(`/admin/customers/${id}`)
      .then((d) => setOuvert(d.customer))
      .catch(() => setErreur('Impossible de charger cette fiche.'));
  };

  const agir = (id: number, chemin: string, body: Record<string, unknown> | undefined, message: string) => {
    setErreur('');

    api(`/admin/customers/${id}/${chemin}`, { method: 'POST', body })
      .then(() => {
        setSucces(message);
        setOuvert(null);
        charger();
      })
      .catch((error: unknown) => {
        setSucces('');
        setErreur(
          error instanceof ApiFailure
            ? (Object.values(error.fields)[0] ?? error.message)
            : 'Action impossible.',
        );
      });
  };

  return (
    <>
      <PageTitle
        title="Clients"
        lead="Consulter, suspendre un compte abusif, ou honorer une demande d'effacement."
        action={
          <div className="relative w-full sm:w-[280px]">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-white/30" />
            <input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Nom, e-mail, téléphone…"
              className={`${inputClass} pl-9`}
            />
          </div>
        }
      />

      <ErrorBanner message={erreur} />
      <SuccessBanner message={succes} />

      {rows === null ? (
        <Loading />
      ) : rows.length === 0 ? (
        <EmptyState title={search ? 'Aucun résultat' : 'Aucun client'}>
          {search ? `Rien ne correspond à « ${search} ».` : 'Les comptes créés sur le site apparaîtront ici.'}
        </EmptyState>
      ) : (
        <div className={`${cardClass} overflow-x-auto`}>
          <table className="w-full min-w-[680px] border-collapse text-left">
            <thead>
              <tr className="border-b border-white/10 text-[9px] uppercase tracking-[0.16em] text-white/35">
                <th className="px-4 py-3 font-bold">Client</th>
                <th className="px-4 py-3 font-bold">Téléphone</th>
                <th className="px-4 py-3 text-right font-bold">Commandes</th>
                <th className="px-4 py-3 text-right font-bold">Total dépensé</th>
                <th className="px-4 py-3 font-bold">Depuis</th>
                <th className="px-4 py-3 font-bold">État</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr
                  key={row.id}
                  onClick={() => ouvrir(row.id)}
                  className="cursor-pointer border-b border-white/[0.06] transition-colors last:border-0 hover:bg-white/[0.04]"
                >
                  <td className="px-4 py-3.5">
                    <span className="block truncate text-[11px] text-[#EDEFF2]">{row.name}</span>
                    <span className="mt-0.5 block truncate text-[10px] text-white/35">{row.email}</span>
                  </td>
                  <td className="px-4 py-3.5 text-[11px] text-white/55">{row.phone || '—'}</td>
                  <td className="px-4 py-3.5 text-right font-display text-[13px] text-[#EDEFF2]">
                    {row.orders_count}
                  </td>
                  <td className="px-4 py-3.5 text-right text-[11px] text-white/70">{formatXof(row.spent_xof)}</td>
                  <td className="px-4 py-3.5 text-[11px] text-white/40">{formatDate(row.created_at)}</td>
                  <td className="px-4 py-3.5">
                    <div className="flex flex-wrap gap-1.5">
                      {row.status !== 'active' && (
                        <Badge tone={row.status === 'suspended' ? 'red' : 'neutral'}>
                          {STATUS_LABEL[row.status]}
                        </Badge>
                      )}
                      {row.status === 'active' && !row.email_verified && <Badge tone="amber">Non vérifié</Badge>}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {ouvert && <CustomerPanel customer={ouvert} onClose={() => setOuvert(null)} onAction={agir} />}
    </>
  );
}

function CustomerPanel({
  customer,
  onClose,
  onAction,
}: {
  customer: CustomerDetail;
  onClose: () => void;
  onAction: (id: number, chemin: string, body: Record<string, unknown> | undefined, message: string) => void;
}) {
  const [confirmeEffacement, setConfirmeEffacement] = useState(false);

  return (
    <div className="fixed inset-0 z-[70] flex justify-end" role="dialog" aria-modal="true">
      <div onClick={onClose} className="absolute inset-0 bg-black/65 backdrop-blur-sm" />

      <aside className="relative flex h-full w-full max-w-md flex-col overflow-y-auto border-l border-white/10 bg-[#0B0C0E]">
        <header className="flex items-start justify-between gap-4 border-b border-white/10 px-6 py-5">
          <div className="min-w-0">
            <p className="truncate font-display text-[15px] uppercase tracking-[0.04em] text-[#EDEFF2]">
              {customer.name}
            </p>
            <p className="mt-1 truncate text-[11px] text-white/45">{customer.email}</p>
          </div>
          <button type="button" onClick={onClose} aria-label="Fermer" className="text-white/40 hover:text-white">
            <X className="h-5 w-5" strokeWidth={2} />
          </button>
        </header>

        <div className="px-6 py-5">
          <div className="flex flex-wrap gap-1.5">
            <Badge tone={customer.status === 'active' ? 'green' : customer.status === 'suspended' ? 'red' : 'neutral'}>
              {STATUS_LABEL[customer.status]}
            </Badge>
            <Badge tone={customer.email_verified ? 'green' : 'amber'}>
              {customer.email_verified ? 'Adresse vérifiée' : 'Adresse non vérifiée'}
            </Badge>
          </div>

          <p className="mt-4 text-[11px] text-white/45">
            {customer.phone || 'Aucun téléphone'} — inscrit le {formatDate(customer.created_at)}
          </p>

          {/* ---------- commandes ---------- */}
          <section className="mt-7">
            <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-white/45">
              Commandes ({customer.orders.length})
            </p>

            {customer.orders.length === 0 ? (
              <p className="mt-2 text-[11px] text-white/30">Aucune commande.</p>
            ) : (
              <ul className="mt-3 flex flex-col gap-2 border-t border-white/10 pt-3">
                {customer.orders.map((order) => (
                  <li key={order.reference} className="flex items-baseline justify-between gap-3 text-[11px]">
                    <span className="truncate font-display tracking-[0.04em] text-[#EDEFF2]">{order.reference}</span>
                    <span className="shrink-0 text-white/35">{formatDate(order.created_at)}</span>
                    <span className="shrink-0 text-white/70">{formatXof(order.total_xof)}</span>
                  </li>
                ))}
              </ul>
            )}
          </section>

          {customer.favorites.length > 0 && (
            <p className="mt-5 text-[11px] text-white/40">
              {customer.favorites.length} article{customer.favorites.length > 1 ? 's' : ''} en favori.
            </p>
          )}

          {/* ---------- actions ---------- */}
          {customer.status !== 'anonymised' && (
            <section className="mt-8 border-t border-white/10 pt-6">
              <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-white/45">Actions</p>

              <div className="mt-3 flex flex-wrap gap-2">
                {customer.status === 'active' ? (
                  <Button
                    variant="danger"
                    onClick={() => onAction(customer.id, 'status', { status: 'suspended' }, 'Compte suspendu.')}
                  >
                    Suspendre
                  </Button>
                ) : (
                  <Button
                    onClick={() => onAction(customer.id, 'status', { status: 'active' }, 'Compte réactivé.')}
                  >
                    Réactiver
                  </Button>
                )}
              </div>

              <p className="mt-2.5 text-[10px] leading-[1.6] text-white/30">
                Suspendre ferme immédiatement les sessions ouvertes du client.
              </p>

              {/* ---------- effacement ---------- */}
              <div className="mt-7 border border-[#E2564A]/25 bg-[#E2564A]/[0.05] p-4">
                <p className="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.14em] text-[#F2A79E]">
                  <ShieldAlert className="h-3.5 w-3.5" strokeWidth={2.2} />
                  Droit à l'effacement
                </p>
                <p className="mt-2 text-[11px] leading-[1.7] text-white/55">
                  Efface le nom, l'adresse, le téléphone et les favoris — y compris dans les commandes déjà
                  passées. Les commandes elles-mêmes sont conservées : ce sont des pièces comptables.
                  <strong className="text-white/80"> Cette action est irréversible.</strong>
                </p>

                {confirmeEffacement ? (
                  <div className="mt-3 flex flex-wrap gap-2">
                    <Button
                      variant="danger"
                      onClick={() => onAction(customer.id, 'anonymise', undefined, 'Compte anonymisé.')}
                    >
                      Confirmer l'anonymisation
                    </Button>
                    <Button variant="ghost" onClick={() => setConfirmeEffacement(false)}>
                      Annuler
                    </Button>
                  </div>
                ) : (
                  <Button variant="ghost" className="mt-3" onClick={() => setConfirmeEffacement(true)}>
                    Anonymiser ce compte
                  </Button>
                )}
              </div>
            </section>
          )}
        </div>
      </aside>
    </div>
  );
}
