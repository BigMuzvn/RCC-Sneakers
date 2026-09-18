import { useEffect, useState } from 'react';
import { Crown, Send, UserPlus } from 'lucide-react';
import { ApiFailure, api } from '../../api/client';
import { useAuth } from '../../context/auth-context';
import {
  Badge,
  Button,
  ErrorBanner,
  Loading,
  PageTitle,
  SuccessBanner,
  TextField,
  cardClass,
  formatDate,
} from './ui';

type Admin = {
  id: number;
  name: string;
  email: string;
  phone: string;
  status: string;
  is_super_admin: boolean;
  email_verified: boolean;
  /** Invité, mais n'a pas encore choisi son mot de passe. */
  pending: boolean;
  created_at: string;
};

const VIDE = { name: '', email: '', phone: '' };

/**
 * Les accès à l'administration.
 *
 * Écran réservé au super administrateur. Un administrateur ajouté tient les
 * commandes, le catalogue, les clients et la messagerie ; il ne peut ni entrer
 * ici, ni toucher aux réglages de la boutique. Sans cette séparation, le
 * premier accès distribué permettrait d'en distribuer d'autres, puis de
 * retirer le sien à celui qui l'a donné.
 */
export default function AdminAdmins() {
  const { customer } = useAuth();
  const [admins, setAdmins] = useState<Admin[] | null>(null);
  const [form, setForm] = useState(VIDE);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [erreur, setErreur] = useState('');
  const [succes, setSucces] = useState('');
  const [enCours, setEnCours] = useState(false);

  const charger = () => {
    api<{ admins: Admin[] }>('/admin/admins')
      .then((data) => setAdmins(data.admins))
      .catch(() => setErreur("Impossible de charger la liste des administrateurs."));
  };

  useEffect(charger, []);

  const ajouter = () => {
    setEnCours(true);
    setErrors({});
    setErreur('');
    setSucces('');

    api<{ created: boolean; message: string }>('/admin/admins', { method: 'POST', body: form })
      .then((data) => {
        setSucces(data.message);
        setForm(VIDE);
        charger();
      })
      .catch((error: unknown) => {
        if (error instanceof ApiFailure) {
          setErrors(error.fields);
          if (Object.keys(error.fields).length === 0) setErreur(error.message);
        } else {
          setErreur("L'ajout n'a pas abouti.");
        }
      })
      .finally(() => setEnCours(false));
  };

  const agir = (id: number, action: 'revoke' | 'resend', message: string) => {
    setErreur('');
    setSucces('');

    api(`/admin/admins/${id}/${action}`, { method: 'POST' })
      .then(() => {
        setSucces(message);
        charger();
      })
      .catch((error: unknown) =>
        setErreur(
          error instanceof ApiFailure
            ? (Object.values(error.fields)[0] ?? error.message)
            : "L'action n'a pas abouti.",
        ),
      );
  };

  if (admins === null) return <Loading />;

  return (
    <>
      <PageTitle
        title="Administrateurs"
        lead="Qui peut entrer dans l'administration. Un accès ajouté ici ne donne pas droit aux réglages de la boutique, ni à cette page."
      />

      <ErrorBanner message={erreur} />
      <SuccessBanner message={succes} />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_340px]">
        {/* ---------- la liste ---------- */}
        <section className="flex flex-col gap-3">
          {admins.map((admin) => (
            <article key={admin.id} className={`${cardClass} p-5`}>
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                  <p className="truncate text-[12px] font-bold uppercase tracking-[0.06em] text-[#EDEFF2]">
                    {admin.name}
                  </p>
                  <p className="mt-0.5 truncate text-[11px] text-white/45">{admin.email}</p>
                  <p className="mt-0.5 truncate text-[10px] text-white/30">
                    {admin.phone || 'Aucun téléphone'} — depuis le {formatDate(admin.created_at)}
                  </p>
                </div>

                <div className="flex shrink-0 flex-wrap items-center gap-1.5">
                  {admin.is_super_admin && (
                    <span className="flex items-center gap-1.5 border border-[#E2B04A]/40 bg-[#E2B04A]/10 px-2.5 py-1 text-[9px] font-bold uppercase tracking-[0.12em] text-[#E2B04A]">
                      <Crown className="h-3 w-3" strokeWidth={2.2} />
                      Super administrateur
                    </span>
                  )}
                  {admin.pending && <Badge tone="amber">Invitation en attente</Badge>}
                  {admin.status !== 'active' && <Badge tone="red">Suspendu</Badge>}
                  {admin.id === customer?.id && <Badge>Vous</Badge>}
                </div>
              </div>

              {/* Le super administrateur ne se retire pas d'ici : ce geste-là
                  passe par le serveur, où il faut un accès à la machine. */}
              {!admin.is_super_admin && (
                <div className="mt-4 flex flex-wrap gap-2 border-t border-white/10 pt-4">
                  {admin.pending && (
                    <Button variant="ghost" onClick={() => agir(admin.id, 'resend', 'Invitation renvoyée.')}>
                      <span className="flex items-center gap-1.5">
                        <Send className="h-3 w-3" strokeWidth={2.2} />
                        Renvoyer l’invitation
                      </span>
                    </Button>
                  )}
                  <Button
                    variant="danger"
                    onClick={() => agir(admin.id, 'revoke', `Accès retiré à ${admin.name}.`)}
                  >
                    Retirer l’accès
                  </Button>
                </div>
              )}
            </article>
          ))}
        </section>

        {/* ---------- ajouter ---------- */}
        <aside className={`${cardClass} h-fit p-5 sm:p-6`}>
          <p className="flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.14em] text-white/45">
            <UserPlus className="h-3.5 w-3.5" strokeWidth={2.2} />
            Ajouter un administrateur
          </p>

          <p className="mt-2.5 text-[10px] leading-[1.6] text-white/35">
            Si cette adresse a déjà un compte client, il est simplement promu. Sinon le compte est créé et la
            personne reçoit un lien pour <strong className="text-white/60">choisir elle-même son mot de passe</strong>.
            Vous n’en fabriquez jamais un pour elle.
          </p>

          <div className="mt-4 flex flex-col gap-4">
            <TextField
              label="E-mail"
              name="email"
              type="email"
              value={form.email}
              onChange={(v) => setForm((c) => ({ ...c, email: v }))}
              error={errors.email}
            />
            <TextField
              label="Nom complet"
              name="name"
              value={form.name}
              onChange={(v) => setForm((c) => ({ ...c, name: v }))}
              error={errors.name}
              hint="Utilisé seulement si le compte doit être créé."
            />
            <TextField
              label="Téléphone"
              name="phone"
              type="tel"
              value={form.phone}
              onChange={(v) => setForm((c) => ({ ...c, phone: v }))}
              error={errors.phone}
            />
          </div>

          <Button onClick={ajouter} disabled={enCours} className="mt-5 w-full">
            {enCours ? 'Envoi…' : 'Ajouter'}
          </Button>

          <p className="mt-5 border-t border-white/10 pt-4 text-[10px] leading-[1.6] text-white/30">
            Un administrateur ajouté voit les commandes, le catalogue, les clients, la messagerie et la vitrine. Il ne
            voit ni les réglages ni cette page. Lui retirer l’accès ferme ses sessions ouvertes sur-le-champ.
          </p>
        </aside>
      </div>
    </>
  );
}
