import { useCallback, useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Download, Mail, RefreshCw, Search } from 'lucide-react';
import { ApiFailure, api } from '../../api/client';
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

type Message = {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  subject: string;
  body: string;
  status: string;
  created_at: string;
};

type Subscriber = {
  id: number;
  email: string;
  status: string;
  source: string;
  synced: boolean;
  created_at: string;
};

export default function AdminInbox() {
  const [params, setParams] = useSearchParams();
  const onglet = params.get('onglet') === 'newsletter' ? 'newsletter' : 'messages';

  const [erreur, setErreur] = useState('');
  const [succes, setSucces] = useState('');

  return (
    <>
      <PageTitle
        title="Messagerie"
        lead="Les messages du formulaire de contact et les inscrits à la lettre d'information."
      />

      <ErrorBanner message={erreur} />
      <SuccessBanner message={succes} />

      <div className="mb-5 flex gap-2">
        {[
          { id: 'messages', label: 'Messages' },
          { id: 'newsletter', label: 'Lettre d’information' },
        ].map((item) => (
          <button
            key={item.id}
            type="button"
            onClick={() => setParams(item.id === 'messages' ? {} : { onglet: item.id }, { replace: true })}
            className={`border px-4 py-2 text-[10px] font-bold uppercase tracking-[0.12em] transition-colors ${
              onglet === item.id
                ? 'border-[#EDEFF2] bg-[#EDEFF2] text-[#17191C]'
                : 'border-white/15 text-white/50 hover:border-white/40 hover:text-white'
            }`}
          >
            {item.label}
          </button>
        ))}
      </div>

      {onglet === 'messages' ? (
        <Messages onError={setErreur} onSuccess={setSucces} />
      ) : (
        <Newsletter onError={setErreur} onSuccess={setSucces} />
      )}
    </>
  );
}

function Messages({ onError, onSuccess }: { onError: (m: string) => void; onSuccess: (m: string) => void }) {
  const [messages, setMessages] = useState<Message[] | null>(null);
  const [filtre, setFiltre] = useState('');

  const charger = useCallback(() => {
    const query = filtre ? `?status=${filtre}` : '';

    api<{ messages: Message[] }>(`/admin/messages${query}`)
      .then((d) => setMessages(d.messages))
      .catch(() => onError('Impossible de charger les messages.'));
  }, [filtre, onError]);

  useEffect(() => {
    charger();
  }, [charger]);

  const marquer = (id: number, status: string) => {
    api(`/admin/messages/${id}/status`, { method: 'POST', body: { status } })
      .then(() => {
        onSuccess(status === 'handled' ? 'Message marqué comme traité.' : 'Message archivé.');
        charger();
      })
      .catch(() => onError('Action impossible.'));
  };

  if (messages === null) return <Loading />;

  return (
    <>
      <div className="mb-4 flex flex-wrap gap-2">
        {[
          { id: '', label: 'Tous' },
          { id: 'new', label: 'Non lus' },
          { id: 'handled', label: 'Traités' },
          { id: 'archived', label: 'Archivés' },
        ].map((item) => (
          <button
            key={item.id}
            type="button"
            onClick={() => setFiltre(item.id)}
            className={`border px-3 py-1.5 text-[9px] font-bold uppercase tracking-[0.12em] transition-colors ${
              filtre === item.id
                ? 'border-white/50 text-white'
                : 'border-white/10 text-white/40 hover:border-white/30 hover:text-white/70'
            }`}
          >
            {item.label}
          </button>
        ))}
      </div>

      {messages.length === 0 ? (
        <EmptyState title="Aucun message">
          Les messages envoyés depuis la page contact arriveront ici, et une notification partira vers l'adresse de
          la boutique.
        </EmptyState>
      ) : (
        <div className="flex flex-col gap-3">
          {messages.map((message) => (
            <article key={message.id} className={`${cardClass} p-5`}>
              <header className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="text-[12px] font-bold uppercase tracking-[0.06em] text-[#EDEFF2]">
                    {message.subject}
                  </p>
                  <p className="mt-1 text-[11px] text-white/45">
                    {message.name} — {formatDate(message.created_at, true)}
                  </p>
                </div>

                <Badge tone={message.status === 'new' ? 'amber' : message.status === 'handled' ? 'green' : 'neutral'}>
                  {message.status === 'new' ? 'Non lu' : message.status === 'handled' ? 'Traité' : 'Archivé'}
                </Badge>
              </header>

              <p className="mt-4 whitespace-pre-wrap border-l-2 border-white/10 pl-4 text-[11px] leading-[1.75] text-white/65">
                {message.body}
              </p>

              <div className="mt-5 flex flex-wrap items-center gap-2">
                <a
                  href={`mailto:${message.email}?subject=${encodeURIComponent('Re : ' + message.subject)}`}
                  className="flex items-center gap-1.5 bg-[#EDEFF2] px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.12em] text-[#17191C] transition-opacity hover:opacity-90"
                >
                  <Mail className="h-3.5 w-3.5" strokeWidth={2.2} />
                  Répondre
                </a>

                {message.phone && (
                  <a
                    href={`tel:${message.phone.replace(/\s/g, '')}`}
                    className="border border-white/20 px-4 py-2.5 text-[10px] font-bold uppercase tracking-[0.12em] text-white transition-colors hover:border-white"
                  >
                    {message.phone}
                  </a>
                )}

                {message.status === 'new' && (
                  <Button variant="ghost" onClick={() => marquer(message.id, 'handled')}>
                    Marquer traité
                  </Button>
                )}

                {message.status !== 'archived' && (
                  <Button variant="ghost" onClick={() => marquer(message.id, 'archived')}>
                    Archiver
                  </Button>
                )}
              </div>
            </article>
          ))}
        </div>
      )}
    </>
  );
}

function Newsletter({ onError, onSuccess }: { onError: (m: string) => void; onSuccess: (m: string) => void }) {
  const [data, setData] = useState<{ subscribers: Subscriber[]; counts: Record<string, number> } | null>(null);
  const [search, setSearch] = useState('');
  const [synchro, setSynchro] = useState(false);

  const charger = useCallback(() => {
    const query = search.trim() ? `?search=${encodeURIComponent(search.trim())}` : '';

    api<{ subscribers: Subscriber[]; counts: Record<string, number> }>(`/admin/newsletter${query}`)
      .then(setData)
      .catch(() => onError('Impossible de charger les inscrits.'));
  }, [search, onError]);

  useEffect(() => {
    const id = window.setTimeout(charger, 300);

    return () => window.clearTimeout(id);
  }, [charger]);

  const desinscrire = (id: number) => {
    api(`/admin/newsletter/${id}/unsubscribe`, { method: 'POST' })
      .then(() => {
        onSuccess('Désinscrit ici et retiré de la liste Brevo.');
        charger();
      })
      .catch(() => onError('Action impossible.'));
  };

  const resynchroniser = () => {
    setSynchro(true);

    api<{ synced: number; remaining: number }>('/admin/newsletter/sync', { method: 'POST' })
      .then((d) => {
        onSuccess(
          d.synced === 0
            ? "Aucun inscrit n'a pu être envoyé. Vérifiez la liste configurée dans config.php."
            : `${d.synced} inscrit(s) envoyés vers Brevo.${d.remaining > 0 ? ` ${d.remaining} en échec.` : ''}`,
        );
        charger();
      })
      .catch((error: unknown) => onError(error instanceof ApiFailure ? error.message : 'Synchronisation impossible.'))
      .finally(() => setSynchro(false));
  };

  /** Export local : rien ne part sur le réseau, le fichier se fabrique dans le navigateur. */
  const exporter = () => {
    if (!data) return;

    const lignes = [
      'email,statut,source,inscrit_le',
      ...data.subscribers.map((s) => `${s.email},${s.status},${s.source},${s.created_at}`),
    ];

    const url = URL.createObjectURL(new Blob([lignes.join('\n')], { type: 'text/csv;charset=utf-8' }));
    const lien = document.createElement('a');
    lien.href = url;
    lien.download = `rcc-newsletter-${new Date().toISOString().slice(0, 10)}.csv`;
    lien.click();
    URL.revokeObjectURL(url);
  };

  if (data === null) return <Loading />;

  return (
    <>
      <div className="mb-4 flex flex-wrap items-center gap-3">
        <div className="relative min-w-0 flex-1 sm:max-w-[280px]">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-white/30" />
          <input
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Rechercher une adresse…"
            className={`${inputClass} pl-9`}
          />
        </div>

        <Button variant="ghost" onClick={exporter}>
          <span className="flex items-center gap-1.5">
            <Download className="h-3.5 w-3.5" strokeWidth={2.2} />
            Exporter
          </span>
        </Button>

        {data.counts.unsynced > 0 && (
          <Button onClick={resynchroniser} disabled={synchro}>
            <span className="flex items-center gap-1.5">
              <RefreshCw className={`h-3.5 w-3.5 ${synchro ? 'animate-spin' : ''}`} strokeWidth={2.2} />
              Renvoyer {data.counts.unsynced} vers Brevo
            </span>
          </Button>
        )}
      </div>

      <p className="mb-4 text-[11px] leading-[1.7] text-white/40">
        {data.counts.subscribed} inscrit{data.counts.subscribed > 1 ? 's' : ''}, {data.counts.unsubscribed}{' '}
        désinscrit{data.counts.unsubscribed > 1 ? 's' : ''}. Les campagnes se composent dans Brevo, où ces adresses
        sont envoyées automatiquement.
      </p>

      {data.subscribers.length === 0 ? (
        <EmptyState title="Aucun inscrit">
          Le formulaire du pied de page alimentera cette liste, et poussera chaque adresse vers Brevo.
        </EmptyState>
      ) : (
        <div className={`${cardClass} overflow-x-auto`}>
          <table className="w-full min-w-[560px] border-collapse text-left">
            <thead>
              <tr className="border-b border-white/10 text-[9px] uppercase tracking-[0.16em] text-white/35">
                <th className="px-4 py-3 font-bold">Adresse</th>
                <th className="px-4 py-3 font-bold">Inscrit le</th>
                <th className="px-4 py-3 font-bold">État</th>
                <th className="px-4 py-3" />
              </tr>
            </thead>
            <tbody>
              {data.subscribers.map((subscriber) => (
                <tr key={subscriber.id} className="border-b border-white/[0.06] last:border-0">
                  <td className="px-4 py-3 text-[11px] text-white/80">{subscriber.email}</td>
                  <td className="px-4 py-3 text-[11px] text-white/40">{formatDate(subscriber.created_at)}</td>
                  <td className="px-4 py-3">
                    <div className="flex flex-wrap gap-1.5">
                      <Badge tone={subscriber.status === 'subscribed' ? 'green' : 'neutral'}>
                        {subscriber.status === 'subscribed' ? 'Inscrit' : 'Désinscrit'}
                      </Badge>
                      {subscriber.status === 'subscribed' && !subscriber.synced && (
                        <Badge tone="amber">Non envoyé</Badge>
                      )}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-right">
                    {subscriber.status === 'subscribed' && (
                      <button
                        type="button"
                        onClick={() => desinscrire(subscriber.id)}
                        className="text-[10px] uppercase tracking-[0.12em] text-white/40 transition-colors hover:text-[#F2A79E]"
                      >
                        Désinscrire
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </>
  );
}
