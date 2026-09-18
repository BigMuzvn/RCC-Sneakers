import { useCallback, useEffect, useRef, useState } from 'react';
import { ImageOff, ImagePlus, Loader2, Plus, Search, X } from 'lucide-react';
import { ApiFailure, api } from '../../api/client';
import { formatXof } from '../../utils/format';
import {
  Badge,
  Button,
  EmptyState,
  ErrorBanner,
  Loading,
  PageTitle,
  SelectField,
  SuccessBanner,
  TextField,
  cardClass,
  inputClass,
  labelClass,
} from './ui';

type Variant = { size: string; stock: number };

type Article = {
  id: number;
  slug: string;
  brand: string;
  colorway: string;
  price_xof: number;
  old_price_xof: number | null;
  is_new_drop: boolean;
  is_active: boolean;
  image: string | null;
  accent: string;
  variants: Variant[];
  stock_total: number;
  sizes: string[];
  // sneakers
  model?: string;
  sku?: string | null;
  category?: string;
  gender?: string;
  description?: string;
  // maillots
  club?: string;
  league?: string;
  kit?: string;
  season?: string;
};

type Kind = 'product' | 'jersey';

const CONFIG = {
  product: {
    route: 'products',
    title: 'Sneakers',
    lead: "Prix, stock, visuel, mise en avant. Retirer un article le masque de la boutique sans toucher aux commandes passées.",
    singular: 'sneaker',
    nameOf: (a: Article) => `${a.brand} ${a.model}`,
  },
  jersey: {
    route: 'jerseys',
    title: 'Maillots',
    lead: 'Clubs, saisons, tailles S à XXL. Les commandes passées gardent le nom et le prix du jour de la vente.',
    singular: 'maillot',
    nameOf: (a: Article) => `${a.club} — ${a.kit}`,
  },
} as const;

const CATEGORIES = [
  { value: 'lifestyle', label: 'Lifestyle' },
  { value: 'running', label: 'Running' },
  { value: 'basketball', label: 'Basketball' },
  { value: 'skate', label: 'Skate' },
];

const GENDERS = [
  { value: 'unisexe', label: 'Unisexe' },
  { value: 'homme', label: 'Homme' },
  { value: 'femme', label: 'Femme' },
];

const KITS = [
  { value: 'Domicile', label: 'Domicile' },
  { value: 'Extérieur', label: 'Extérieur' },
  { value: 'Third', label: 'Third' },
];

const LEAGUES = ['La Liga', 'Premier League', 'Ligue 1', 'Bundesliga', 'Serie A', 'Sélections'].map((l) => ({
  value: l,
  label: l,
}));

export default function AdminCatalogue({ kind }: { kind: Kind }) {
  const config = CONFIG[kind];

  const [articles, setArticles] = useState<Article[] | null>(null);
  const [search, setSearch] = useState('');
  const [archives, setArchives] = useState(false);
  const [edite, setEdite] = useState<Article | 'nouveau' | null>(null);
  const [erreur, setErreur] = useState('');
  const [succes, setSucces] = useState('');

  const charger = useCallback(() => {
    const query = new URLSearchParams();
    if (search.trim()) query.set('search', search.trim());
    if (archives) query.set('include_archived', '1');

    api<Record<string, Article[]>>(`/admin/${config.route}?${query}`)
      .then((d) => setArticles(d[config.route]))
      .catch(() => setErreur('Impossible de charger le catalogue.'));
  }, [config.route, search, archives]);

  useEffect(() => {
    setArticles(null);
    const id = window.setTimeout(charger, 300);

    return () => window.clearTimeout(id);
  }, [charger]);

  return (
    <>
      <PageTitle
        title={config.title}
        lead={config.lead}
        action={
          <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
            <div className="relative min-w-0 flex-1 sm:w-[240px] sm:flex-none">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-white/30" />
              <input
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Rechercher…"
                className={`${inputClass} pl-9`}
              />
            </div>
            <Button onClick={() => setEdite('nouveau')}>
              <span className="flex items-center gap-1.5">
                <Plus className="h-3.5 w-3.5" strokeWidth={2.4} />
                Ajouter
              </span>
            </Button>
          </div>
        }
      />

      <ErrorBanner message={erreur} />
      <SuccessBanner message={succes} />

      <label className="mb-4 inline-flex cursor-pointer items-center gap-2">
        <input
          type="checkbox"
          checked={archives}
          onChange={(event) => setArchives(event.target.checked)}
          className="h-3.5 w-3.5 accent-[#EDEFF2]"
        />
        <span className="text-[10px] uppercase tracking-[0.12em] text-white/45">
          Afficher aussi les articles retirés
        </span>
      </label>

      {articles === null ? (
        <Loading />
      ) : articles.length === 0 ? (
        <EmptyState title={search ? 'Aucun résultat' : 'Catalogue vide'}>
          {search ? `Rien ne correspond à « ${search} ».` : `Ajoutez un premier ${config.singular}.`}
        </EmptyState>
      ) : (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {articles.map((article) => (
            <button
              key={article.id}
              type="button"
              onClick={() => setEdite(article)}
              className={`${cardClass} group flex gap-4 p-4 text-left transition-colors hover:border-white/30 ${
                article.is_active ? '' : 'opacity-50'
              }`}
            >
              <Vignette article={article} />

              <div className="min-w-0 flex-1">
                <p className="truncate text-[11px] font-bold uppercase tracking-[0.06em] text-[#EDEFF2]">
                  {config.nameOf(article)}
                </p>
                <p className="mt-0.5 truncate text-[10px] text-white/40">{article.colorway}</p>

                <p className="mt-2 font-display text-[14px] text-[#EDEFF2]">{formatXof(article.price_xof)}</p>

                <div className="mt-2.5 flex flex-wrap gap-1.5">
                  {!article.is_active && <Badge tone="red">Retiré</Badge>}
                  {article.is_new_drop && <Badge>Nouveau</Badge>}
                  <Badge tone={article.stock_total === 0 ? 'red' : article.stock_total < 5 ? 'amber' : 'green'}>
                    {article.stock_total} en stock
                  </Badge>
                  {!article.image && <Badge tone="amber">Sans visuel</Badge>}
                </div>
              </div>
            </button>
          ))}
        </div>
      )}

      {edite !== null && (
        <ArticleForm
          kind={kind}
          article={edite === 'nouveau' ? null : edite}
          onClose={() => setEdite(null)}
          onSaved={(message) => {
            setEdite(null);
            setSucces(message);
            setErreur('');
            charger();
          }}
        />
      )}
    </>
  );
}

/**
 * Aperçu : le visuel s'il existe, sinon un halo dans la couleur de l'article.
 *
 * Une référence qui ne se charge pas est **dite**, et non masquée. Masquer
 * confond deux situations opposées : un article auquel on n'a pas encore donné
 * de visuel, et un article dont le visuel manque sur le serveur. La première
 * attend une photo, la seconde attend une réparation.
 */
function Vignette({ article }: { article: Article }) {
  const [cassee, setCassee] = useState(false);

  // Un envoi réussi doit effacer l'avertissement : sans cela, la vignette
  // resterait marquée « introuvable » après avoir été réparée.
  useEffect(() => setCassee(false), [article.image]);

  return (
    <div
      className="relative h-[72px] w-[72px] shrink-0 overflow-hidden border border-white/10"
      style={{
        background: `radial-gradient(ellipse 80% 70% at 50% 56%, ${article.accent}8C 0%, ${article.accent}33 55%, transparent 80%)`,
      }}
    >
      {article.image && !cassee && (
        <img
          src={`/api/uploads/${article.image}`}
          alt=""
          loading="lazy"
          className="absolute inset-0 h-full w-full object-contain p-1"
          onError={() => setCassee(true)}
        />
      )}

      {article.image && cassee && (
        <span
          title={`Le fichier « ${article.image} » est absent du serveur.`}
          className="absolute inset-0 flex items-center justify-center bg-[#0B0C0E]/70 text-[#E2B04A]"
        >
          <ImageOff className="h-5 w-5" strokeWidth={2} />
        </span>
      )}
    </div>
  );
}

function ArticleForm({
  kind,
  article,
  onClose,
  onSaved,
}: {
  kind: Kind;
  article: Article | null;
  onClose: () => void;
  onSaved: (message: string) => void;
}) {
  const config = CONFIG[kind];
  const creation = article === null;
  const fileInput = useRef<HTMLInputElement>(null);

  const [values, setValues] = useState<Record<string, string>>(() => ({
    brand: article?.brand ?? '',
    model: article?.model ?? '',
    sku: article?.sku ?? '',
    category: article?.category ?? 'lifestyle',
    gender: article?.gender ?? 'unisexe',
    description: article?.description ?? '',
    club: article?.club ?? '',
    league: article?.league ?? 'La Liga',
    kit: article?.kit ?? 'Domicile',
    season: article?.season ?? '2025/26',
    colorway: article?.colorway ?? '',
    price_xof: String(article?.price_xof ?? ''),
    old_price_xof: article?.old_price_xof ? String(article.old_price_xof) : '',
    accent: article?.accent ?? '#808080',
    slug: article?.slug ?? '',
  }));

  const [flags, setFlags] = useState({
    is_new_drop: article?.is_new_drop ?? false,
    is_active: article?.is_active ?? true,
  });

  const [image, setImage] = useState<string | null>(article?.image ?? null);
  const [stock, setStock] = useState<Record<string, string>>(() =>
    Object.fromEntries((article?.variants ?? []).map((v) => [v.size, String(v.stock)])),
  );

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [notice, setNotice] = useState('');
  const [enCours, setEnCours] = useState(false);
  const [envoiImage, setEnvoiImage] = useState(false);

  const set = (field: string, value: string) => setValues((current) => ({ ...current, [field]: value }));

  const televerser = (file: File) => {
    setEnvoiImage(true);
    setNotice('');

    const data = new FormData();
    data.append('image', file);

    // FormData impose son propre en-tête avec la frontière multipart : le
    // client d'API force application/json, on passe donc par fetch directement.
    fetch('/api/admin/uploads', { method: 'POST', credentials: 'include', body: data })
      .then(async (response) => {
        const payload = await response.json().catch(() => null);

        if (!response.ok) {
          throw new Error(payload?.error?.fields?.image ?? payload?.error?.message ?? 'Envoi impossible.');
        }

        setImage(payload.data.image);
      })
      .catch((error: unknown) => setNotice(error instanceof Error ? error.message : 'Envoi impossible.'))
      .finally(() => setEnvoiImage(false));
  };

  const enregistrer = () => {
    setEnCours(true);
    setErrors({});
    setNotice('');

    const body: Record<string, unknown> = {
      colorway: values.colorway,
      price_xof: values.price_xof === '' ? '' : Number(values.price_xof),
      old_price_xof: values.old_price_xof === '' ? null : Number(values.old_price_xof),
      accent: values.accent,
      image,
      is_new_drop: flags.is_new_drop,
      is_active: flags.is_active,
      slug: values.slug,
    };

    if (kind === 'product') {
      Object.assign(body, {
        brand: values.brand,
        model: values.model,
        sku: values.sku,
        category: values.category,
        gender: values.gender,
        description: values.description,
      });
    } else {
      Object.assign(body, {
        club: values.club,
        league: values.league,
        brand: values.brand,
        kit: values.kit,
        season: values.season,
      });
    }

    const path = creation ? `/admin/${config.route}` : `/admin/${config.route}/${article.id}`;

    api<{ id: number }>(path, { method: 'POST', body })
      .then(async (data) => {
        // Le stock est enregistré séparément : il a sa propre route, et un
        // article neuf n'a pas encore de tailles avant sa création.
        const quantites = Object.fromEntries(
          Object.entries(stock)
            .filter(([, value]) => value !== '')
            .map(([size, value]) => [size, Number(value)]),
        );

        if (Object.keys(quantites).length > 0) {
          await api(`/admin/${config.route}/${creation ? data.id : article.id}/stock`, {
            method: 'POST',
            body: { stock: quantites },
          }).catch(() => undefined);
        }

        onSaved(creation ? `${config.singular} ajouté.` : 'Modifications enregistrées.');
      })
      .catch((error: unknown) => {
        setEnCours(false);

        if (error instanceof ApiFailure) {
          setErrors(error.fields);
          if (Object.keys(error.fields).length === 0) setNotice(error.message);
          if (error.fields.form) setNotice(error.fields.form);
        } else {
          setNotice('Une erreur est survenue.');
        }
      });
  };

  const tailles = article?.sizes ?? (kind === 'product' ? ['39', '40', '41', '42', '43', '44', '45'] : ['S', 'M', 'L', 'XL', 'XXL']);

  return (
    <div className="fixed inset-0 z-[70] flex justify-end" role="dialog" aria-modal="true">
      <div onClick={onClose} className="absolute inset-0 bg-black/65 backdrop-blur-sm" />

      <aside className="relative flex h-full w-full max-w-lg flex-col overflow-y-auto border-l border-white/10 bg-[#0B0C0E]">
        <header className="sticky top-0 z-10 flex items-center justify-between gap-4 border-b border-white/10 bg-[#0B0C0E] px-6 py-5">
          <p className="font-display text-[14px] uppercase tracking-[0.06em] text-[#EDEFF2]">
            {creation ? `Nouveau ${config.singular}` : config.nameOf(article)}
          </p>
          <button type="button" onClick={onClose} aria-label="Fermer" className="text-white/40 hover:text-white">
            <X className="h-5 w-5" strokeWidth={2} />
          </button>
        </header>

        <form
          className="flex flex-col gap-4 px-6 py-6"
          onSubmit={(event) => {
            event.preventDefault();
            enregistrer();
          }}
        >
          <ErrorBanner message={notice} />

          {/* ---------- visuel ---------- */}
          <div>
            <p className={labelClass}>Visuel</p>
            <div className="mt-2 flex items-center gap-4">
              <div
                className="relative h-[92px] w-[92px] shrink-0 overflow-hidden border border-white/15"
                style={{
                  background: `radial-gradient(ellipse 80% 70% at 50% 56%, ${values.accent}8C 0%, ${values.accent}33 55%, transparent 80%)`,
                }}
              >
                {image && (
                  <img
                    src={`/api/uploads/${image}`}
                    alt=""
                    className="absolute inset-0 h-full w-full object-contain p-1"
                    onError={(event) => {
                      event.currentTarget.style.display = 'none';
                    }}
                  />
                )}
                {envoiImage && (
                  <span className="absolute inset-0 flex items-center justify-center bg-black/60">
                    <Loader2 className="h-4 w-4 animate-spin text-white" strokeWidth={2.2} />
                  </span>
                )}
              </div>

              <div className="min-w-0 flex-1">
                <input
                  ref={fileInput}
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  className="hidden"
                  onChange={(event) => {
                    const file = event.target.files?.[0];
                    if (file) televerser(file);
                    event.target.value = '';
                  }}
                />
                <Button variant="ghost" onClick={() => fileInput.current?.click()} disabled={envoiImage}>
                  <span className="flex items-center gap-1.5">
                    <ImagePlus className="h-3.5 w-3.5" strokeWidth={2.2} />
                    {image ? 'Remplacer' : 'Choisir une image'}
                  </span>
                </Button>
                {image && (
                  <button
                    type="button"
                    onClick={() => setImage(null)}
                    className="ml-3 text-[10px] uppercase tracking-[0.12em] text-white/40 hover:text-white"
                  >
                    Retirer
                  </button>
                )}
                <p className="mt-2 text-[10px] leading-[1.5] text-white/30">
                  PNG détouré de préférence. L'image est convertie et allégée automatiquement.
                </p>
              </div>
            </div>
          </div>

          {/* ---------- identité ---------- */}
          {kind === 'product' ? (
            <>
              <div className="grid grid-cols-2 gap-3">
                <TextField label="Marque" name="brand" value={values.brand} onChange={(v) => set('brand', v)} error={errors.brand} />
                <TextField label="Modèle" name="model" value={values.model} onChange={(v) => set('model', v)} error={errors.model} />
              </div>
              <TextField label="Coloris" name="colorway" value={values.colorway} onChange={(v) => set('colorway', v)} error={errors.colorway} />
              <div className="grid grid-cols-2 gap-3">
                <SelectField label="Catégorie" value={values.category} onChange={(v) => set('category', v)} options={CATEGORIES} error={errors.category} />
                <SelectField label="Genre" value={values.gender} onChange={(v) => set('gender', v)} options={GENDERS} error={errors.gender} />
              </div>
              <TextField
                label="Référence fabricant"
                name="sku"
                value={values.sku}
                onChange={(v) => set('sku', v)}
                error={errors.sku}
                hint="Facultatif. Laissez vide plutôt que d'inventer."
              />
              <label className="flex flex-col gap-1.5">
                <span className={labelClass}>Description</span>
                <textarea
                  rows={4}
                  value={values.description}
                  onChange={(event) => set('description', event.target.value)}
                  className={`${inputClass} resize-none ${errors.description ? 'border-[#E2564A]/70' : ''}`}
                />
                {errors.description && <span className="text-[10px] text-[#E2564A]">{errors.description}</span>}
              </label>
            </>
          ) : (
            <>
              <TextField label="Club ou sélection" name="club" value={values.club} onChange={(v) => set('club', v)} error={errors.club} />
              <div className="grid grid-cols-2 gap-3">
                <SelectField label="Championnat" value={values.league} onChange={(v) => set('league', v)} options={LEAGUES} error={errors.league} />
                <SelectField label="Tenue" value={values.kit} onChange={(v) => set('kit', v)} options={KITS} error={errors.kit} />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <TextField label="Équipementier" name="brand" value={values.brand} onChange={(v) => set('brand', v)} error={errors.brand} />
                <TextField label="Saison" name="season" value={values.season} onChange={(v) => set('season', v)} error={errors.season} />
              </div>
              <TextField label="Coloris" name="colorway" value={values.colorway} onChange={(v) => set('colorway', v)} error={errors.colorway} />
            </>
          )}

          {/* ---------- prix ---------- */}
          <div className="grid grid-cols-2 gap-3">
            <TextField
              label="Prix (F CFA)"
              name="price_xof"
              type="number"
              value={values.price_xof}
              onChange={(v) => set('price_xof', v)}
              error={errors.price_xof}
            />
            <TextField
              label="Prix barré"
              name="old_price_xof"
              type="number"
              value={values.old_price_xof}
              onChange={(v) => set('old_price_xof', v)}
              error={errors.old_price_xof}
              hint="Vide si pas en solde."
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <label className="flex flex-col gap-1.5">
              <span className={labelClass}>Couleur d'accent</span>
              <div className="flex items-center gap-2">
                <input
                  type="color"
                  value={values.accent}
                  onChange={(event) => set('accent', event.target.value.toUpperCase())}
                  className="h-[38px] w-[46px] shrink-0 cursor-pointer border border-white/15 bg-transparent"
                />
                <input
                  value={values.accent}
                  onChange={(event) => set('accent', event.target.value.toUpperCase())}
                  className={`${inputClass} ${errors.accent ? 'border-[#E2564A]/70' : ''}`}
                />
              </div>
              {errors.accent ? (
                <span className="text-[10px] text-[#E2564A]">{errors.accent}</span>
              ) : (
                <span className="text-[10px] leading-[1.5] text-white/30">
                  Elle éclaire la carte et la fiche. Prenez la couleur dominante du visuel.
                </span>
              )}
            </label>

            <TextField
              label="Adresse de la page"
              name="slug"
              value={values.slug}
              onChange={(v) => set('slug', v)}
              error={errors.slug}
              hint="Vide : déduite du nom."
            />
          </div>

          <div className="flex flex-wrap gap-5">
            <label className="flex cursor-pointer items-center gap-2">
              <input
                type="checkbox"
                checked={flags.is_new_drop}
                onChange={(event) => setFlags((f) => ({ ...f, is_new_drop: event.target.checked }))}
                className="h-3.5 w-3.5 accent-[#EDEFF2]"
              />
              <span className="text-[10px] uppercase tracking-[0.12em] text-white/60">Marquer « Nouveau »</span>
            </label>

            <label className="flex cursor-pointer items-center gap-2">
              <input
                type="checkbox"
                checked={flags.is_active}
                onChange={(event) => setFlags((f) => ({ ...f, is_active: event.target.checked }))}
                className="h-3.5 w-3.5 accent-[#EDEFF2]"
              />
              <span className="text-[10px] uppercase tracking-[0.12em] text-white/60">En vente</span>
            </label>
          </div>

          {/* ---------- stock ---------- */}
          <div>
            <p className={labelClass}>Stock par taille</p>
            <div className={`mt-2 grid gap-2 ${kind === 'product' ? 'grid-cols-4 sm:grid-cols-7' : 'grid-cols-5'}`}>
              {tailles.map((size) => (
                <label key={size} className="flex flex-col gap-1">
                  <span className="text-center text-[10px] font-bold text-white/50">{size}</span>
                  <input
                    type="number"
                    min={0}
                    value={stock[size] ?? '0'}
                    onChange={(event) => setStock((current) => ({ ...current, [size]: event.target.value }))}
                    className="w-full border border-white/15 bg-white/[0.03] px-1 py-2 text-center text-[12px] text-white focus:border-white/50 focus:outline-none"
                  />
                </label>
              ))}
            </div>
            {errors.stock && <p className="mt-1.5 text-[10px] text-[#E2564A]">{errors.stock}</p>}
          </div>

          <div className="sticky bottom-0 -mx-6 mt-2 flex gap-3 border-t border-white/10 bg-[#0B0C0E] px-6 py-4">
            <Button type="submit" disabled={enCours} className="flex-1">
              {enCours ? 'Enregistrement…' : creation ? 'Créer' : 'Enregistrer'}
            </Button>
            <Button variant="ghost" onClick={onClose}>
              Annuler
            </Button>
          </div>
        </form>
      </aside>
    </div>
  );
}
