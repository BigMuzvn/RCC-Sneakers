import { useEffect, useState } from 'react';
import { ArrowDown, ArrowUp, Search } from 'lucide-react';
import { ApiFailure, api } from '../../api/client';
import { formatXof } from '../../utils/format';
import {
  Badge,
  Button,
  ErrorBanner,
  Loading,
  PageTitle,
  SuccessBanner,
  TextField,
  cardClass,
  inputClass,
  labelClass,
} from './ui';

type Setting = { name: string; label: string; type: string; value: string };

type Featured = {
  slug: string;
  found: boolean;
  active: boolean;
  title: string | null;
  colorway: string | null;
  image: string | null;
  accent: string | null;
  price_xof: number | null;
};

type Zone = { id: string; label: string; delay_label: string; fee_xof: number; is_active: boolean };

type Payload = {
  settings: Setting[];
  featured: Featured[];
  featured_count: number;
  delivery_zones: Zone[];
};

/** Deux écrans, un seul appel : la vitrine et les réglages viennent ensemble. */
export default function AdminSettings({ view }: { view: 'vitrine' | 'reglages' }) {
  const [data, setData] = useState<Payload | null>(null);
  const [erreur, setErreur] = useState('');
  const [succes, setSucces] = useState('');

  const charger = () => {
    api<Payload>('/admin/settings')
      .then(setData)
      .catch(() => setErreur('Impossible de charger les réglages.'));
  };

  useEffect(charger, []);

  if (data === null) return <Loading />;

  return view === 'vitrine' ? (
    <Vitrine data={data} onReload={charger} erreur={erreur} succes={succes} setErreur={setErreur} setSucces={setSucces} />
  ) : (
    <Reglages data={data} onReload={charger} erreur={erreur} succes={succes} setErreur={setErreur} setSucces={setSucces} />
  );
}

type SousProps = {
  data: Payload;
  onReload: () => void;
  erreur: string;
  succes: string;
  setErreur: (m: string) => void;
  setSucces: (m: string) => void;
};

/* ------------------------------------------------------------------ vitrine */

type Candidat = { slug: string; brand: string; model: string; colorway: string; image: string | null; accent: string };

function Vitrine({ data, onReload, erreur, succes, setErreur, setSucces }: SousProps) {
  const [slugs, setSlugs] = useState<string[]>(data.featured.map((f) => f.slug));
  const [candidats, setCandidats] = useState<Candidat[]>([]);
  const [recherche, setRecherche] = useState('');
  const [enCours, setEnCours] = useState(false);

  useEffect(() => {
    api<{ products: Candidat[] }>('/admin/products')
      .then((d) => setCandidats(d.products))
      .catch(() => undefined);
  }, []);

  const fiche = (slug: string) =>
    candidats.find((c) => c.slug === slug) ?? {
      slug,
      brand: '',
      model: data.featured.find((f) => f.slug === slug)?.title ?? slug,
      colorway: '',
      image: null,
      accent: '#808080',
    };

  const deplacer = (index: number, sens: -1 | 1) => {
    const cible = index + sens;
    if (cible < 0 || cible >= slugs.length) return;

    const copie = [...slugs];
    [copie[index], copie[cible]] = [copie[cible], copie[index]];
    setSlugs(copie);
  };

  const remplacer = (index: number, slug: string) => {
    const copie = [...slugs];
    copie[index] = slug;
    setSlugs(copie);
  };

  const enregistrer = () => {
    setEnCours(true);
    setErreur('');
    setSucces('');

    api('/admin/featured', { method: 'POST', body: { slugs } })
      .then(() => {
        setSucces("Vitrine mise à jour. L'accueil affiche ces quatre paires.");
        onReload();
      })
      .catch((error: unknown) => {
        setErreur(error instanceof ApiFailure ? (error.fields.slugs ?? error.message) : 'Enregistrement impossible.');
      })
      .finally(() => setEnCours(false));
  };

  const filtres = candidats.filter((c) =>
    recherche.trim() === ''
      ? true
      : `${c.brand} ${c.model} ${c.colorway}`.toLowerCase().includes(recherche.trim().toLowerCase()),
  );

  return (
    <>
      <PageTitle
        title="Vitrine"
        lead="Les quatre paires du carrousel d'accueil, dans l'ordre d'affichage. Ce sont de vrais produits : l'achat depuis l'accueil ajoute exactement l'article montré."
      />

      <ErrorBanner message={erreur} />
      <SuccessBanner message={succes} />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_320px]">
        <div className="flex flex-col gap-3">
          {slugs.map((slug, index) => {
            const item = fiche(slug);
            const meta = data.featured.find((f) => f.slug === slug);
            const manquant = meta !== undefined && !meta.found;

            return (
              <div
                key={`${slug}-${index}`}
                className={`${cardClass} flex items-center gap-4 p-4 ${manquant ? 'border-[#E2564A]/40' : ''}`}
              >
                <span className="w-5 shrink-0 text-center font-display text-[16px] text-white/25">{index + 1}</span>

                <div
                  className="relative h-[60px] w-[60px] shrink-0 overflow-hidden border border-white/10"
                  style={{
                    background: `radial-gradient(ellipse 80% 70% at 50% 56%, ${item.accent}8C 0%, ${item.accent}33 55%, transparent 80%)`,
                  }}
                >
                  {item.image && (
                    <img
                      src={`/api/uploads/${item.image}`}
                      alt=""
                      className="absolute inset-0 h-full w-full object-contain p-1"
                      onError={(event) => {
                        event.currentTarget.style.display = 'none';
                      }}
                    />
                  )}
                </div>

                <div className="min-w-0 flex-1">
                  <select
                    value={slug}
                    onChange={(event) => remplacer(index, event.target.value)}
                    className={`${inputClass} truncate`}
                  >
                    {!candidats.some((c) => c.slug === slug) && (
                      <option value={slug} className="bg-[#17191C]">
                        {slug} (introuvable)
                      </option>
                    )}
                    {candidats.map((candidat) => (
                      <option key={candidat.slug} value={candidat.slug} className="bg-[#17191C]">
                        {candidat.brand} {candidat.model} — {candidat.colorway}
                      </option>
                    ))}
                  </select>

                  {manquant && (
                    <p className="mt-1.5 text-[10px] text-[#F2A79E]">
                      Cet article n'existe plus ou a été retiré de la vente. L'accueil affiche un vide à sa place.
                    </p>
                  )}
                </div>

                <div className="flex shrink-0 flex-col gap-1">
                  <button
                    type="button"
                    onClick={() => deplacer(index, -1)}
                    disabled={index === 0}
                    aria-label="Monter"
                    className="border border-white/15 p-1.5 text-white/50 transition-colors hover:border-white/40 hover:text-white disabled:opacity-25"
                  >
                    <ArrowUp className="h-3 w-3" strokeWidth={2.4} />
                  </button>
                  <button
                    type="button"
                    onClick={() => deplacer(index, 1)}
                    disabled={index === slugs.length - 1}
                    aria-label="Descendre"
                    className="border border-white/15 p-1.5 text-white/50 transition-colors hover:border-white/40 hover:text-white disabled:opacity-25"
                  >
                    <ArrowDown className="h-3 w-3" strokeWidth={2.4} />
                  </button>
                </div>
              </div>
            );
          })}

          <Button onClick={enregistrer} disabled={enCours} className="mt-1 self-start">
            {enCours ? 'Enregistrement…' : 'Enregistrer la vitrine'}
          </Button>
        </div>

        <aside className={`${cardClass} h-fit p-5`}>
          <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-white/45">Catalogue disponible</p>

          <div className="relative mt-3">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-white/30" />
            <input
              value={recherche}
              onChange={(event) => setRecherche(event.target.value)}
              placeholder="Filtrer…"
              className={`${inputClass} pl-9`}
            />
          </div>

          <ul className="mt-3 max-h-[360px] overflow-y-auto">
            {filtres.map((candidat) => (
              <li key={candidat.slug} className="border-b border-white/[0.06] py-2 last:border-0">
                <p className="truncate text-[11px] text-white/70">
                  {candidat.brand} {candidat.model}
                </p>
                <p className="truncate text-[10px] text-white/30">{candidat.colorway}</p>
              </li>
            ))}
          </ul>

          <p className="mt-4 text-[10px] leading-[1.6] text-white/30">
            Un article retiré de la vente ne peut pas être mis en avant : l'accueil afficherait un vide là où tout le
            monde regarde en premier.
          </p>
        </aside>
      </div>
    </>
  );
}

/* ----------------------------------------------------------------- réglages */

function Reglages({ data, onReload, erreur, succes, setErreur, setSucces }: SousProps) {
  const [values, setValues] = useState<Record<string, string>>(() =>
    Object.fromEntries(data.settings.map((s) => [s.name, s.value])),
  );
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [zones, setZones] = useState<Zone[]>(data.delivery_zones);
  const [enCours, setEnCours] = useState(false);

  const enregistrer = () => {
    setEnCours(true);
    setErrors({});
    setErreur('');
    setSucces('');

    api('/admin/settings', { method: 'POST', body: values })
      .then(() => {
        setSucces('Réglages enregistrés.');
        onReload();
      })
      .catch((error: unknown) => {
        if (error instanceof ApiFailure) {
          setErrors(error.fields);
          if (Object.keys(error.fields).length === 0) setErreur(error.message);
        } else {
          setErreur('Enregistrement impossible.');
        }
      })
      .finally(() => setEnCours(false));
  };

  const enregistrerZone = (zone: Zone) => {
    setErreur('');

    api('/admin/delivery-zones/' + zone.id, {
      method: 'POST',
      body: { label: zone.label, delay_label: zone.delay_label, fee_xof: Number(zone.fee_xof), is_active: zone.is_active },
    })
      .then(() => setSucces(`Zone « ${zone.label} » enregistrée.`))
      .catch((error: unknown) =>
        setErreur(error instanceof ApiFailure ? (Object.values(error.fields)[0] ?? error.message) : 'Enregistrement impossible.'),
      );
  };

  return (
    <>
      <PageTitle
        title="Réglages"
        lead="Coordonnées affichées sur le site et tarifs de livraison. Tout ceci était écrit en dur dans le code."
      />

      <ErrorBanner message={erreur} />
      <SuccessBanner message={succes} />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <section className={`${cardClass} p-5 sm:p-6`}>
          <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-white/45">Boutique</p>

          <div className="mt-4 flex flex-col gap-4">
            {data.settings.map((setting) => (
              <TextField
                key={setting.name}
                label={setting.label}
                name={setting.name}
                type={setting.type === 'email' ? 'email' : 'text'}
                value={values[setting.name] ?? ''}
                onChange={(v) => setValues((current) => ({ ...current, [setting.name]: v }))}
                error={errors[setting.name]}
                hint={
                  setting.name === 'shop_notification_email'
                    ? 'Reçoit une notification à chaque commande et chaque message.'
                    : setting.type === 'url'
                      ? 'Adresse complète, commençant par https://'
                      : undefined
                }
              />
            ))}
          </div>

          <Button onClick={enregistrer} disabled={enCours} className="mt-5">
            {enCours ? 'Enregistrement…' : 'Enregistrer'}
          </Button>
        </section>

        <section className={`${cardClass} p-5 sm:p-6`}>
          <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-white/45">Zones de livraison</p>
          <p className="mt-2 text-[10px] leading-[1.6] text-white/30">
            Le montant appliqué à une commande est celui enregistré ici — le navigateur ne peut pas l'influencer.
          </p>

          <div className="mt-4 flex flex-col gap-4">
            {zones.map((zone, index) => (
              <div key={zone.id} className="border border-white/10 p-4">
                <div className="flex items-center justify-between gap-3">
                  <p className="text-[11px] font-bold uppercase tracking-[0.1em] text-[#EDEFF2]">{zone.label}</p>
                  <Badge tone={zone.is_active ? 'green' : 'neutral'}>{zone.is_active ? 'Active' : 'Inactive'}</Badge>
                </div>

                <div className="mt-3 grid grid-cols-2 gap-3">
                  <label className="flex flex-col gap-1.5">
                    <span className={labelClass}>Libellé</span>
                    <input
                      value={zone.label}
                      onChange={(event) =>
                        setZones((c) => c.map((z, i) => (i === index ? { ...z, label: event.target.value } : z)))
                      }
                      className={inputClass}
                    />
                  </label>
                  <label className="flex flex-col gap-1.5">
                    <span className={labelClass}>Délai</span>
                    <input
                      value={zone.delay_label}
                      onChange={(event) =>
                        setZones((c) => c.map((z, i) => (i === index ? { ...z, delay_label: event.target.value } : z)))
                      }
                      className={inputClass}
                    />
                  </label>
                </div>

                <div className="mt-3 flex items-end gap-3">
                  <label className="flex flex-1 flex-col gap-1.5">
                    <span className={labelClass}>Frais (F CFA)</span>
                    <input
                      type="number"
                      min={0}
                      value={zone.fee_xof}
                      onChange={(event) =>
                        setZones((c) =>
                          c.map((z, i) => (i === index ? { ...z, fee_xof: Number(event.target.value) } : z)),
                        )
                      }
                      className={inputClass}
                    />
                  </label>

                  <label className="flex cursor-pointer items-center gap-2 pb-3">
                    <input
                      type="checkbox"
                      checked={zone.is_active}
                      onChange={(event) =>
                        setZones((c) => c.map((z, i) => (i === index ? { ...z, is_active: event.target.checked } : z)))
                      }
                      className="h-3.5 w-3.5 accent-[#EDEFF2]"
                    />
                    <span className="text-[10px] uppercase tracking-[0.12em] text-white/55">Proposée</span>
                  </label>

                  <Button variant="ghost" onClick={() => enregistrerZone(zone)} className="mb-[1px]">
                    Enregistrer
                  </Button>
                </div>

                <p className="mt-2 text-[10px] text-white/25">Actuellement facturé {formatXof(zone.fee_xof)}.</p>
              </div>
            ))}
          </div>
        </section>

      </div>
    </>
  );
}
