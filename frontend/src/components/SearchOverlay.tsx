import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Search, X } from 'lucide-react';
import { useCatalogue } from '../context/catalogue-context';
import { formatXof } from '../utils/format';

/**
 * La recherche.
 *
 * Le bouton de la barre n'était relié à rien : une loupe sur toutes les pages
 * du site, qui ne faisait rien quand on cliquait dessus. Une commande inerte
 * coûte plus de confiance qu'une commande absente.
 *
 * Tout se passe dans le navigateur : le catalogue est déjà chargé, il n'y a
 * donc rien à demander au serveur et les résultats arrivent à la frappe. Une
 * route de recherche côté serveur n'aurait rien apporté sur trente articles,
 * sinon une attente.
 */

/** Ce sur quoi on cherche, réduit d'avance pour ne pas le recalculer à chaque frappe. */
type Entree = {
  cle: string;
  to: string;
  titre: string;
  detail: string;
  prix: number;
  image: string | null;
  accent: string;
  /** Tous les mots de la fiche, sans accents ni majuscules. */
  index: string;
};

/**
 * « Sélections » doit se trouver en tapant « selections », et « Bénin » en
 * tapant « benin » : personne ne compose un accent dans un champ de recherche.
 */
const sansAccents = (valeur: string) =>
  valeur
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

export default function SearchOverlay({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { products, jerseys } = useCatalogue();
  const navigate = useNavigate();
  const [terme, setTerme] = useState('');
  const champ = useRef<HTMLInputElement>(null);

  const entrees = useMemo<Entree[]>(
    () => [
      ...products.map((p) => ({
        cle: `sneaker-${p.id}`,
        to: `/boutique/${p.slug}`,
        titre: `${p.brand} ${p.model}`,
        detail: p.colorway,
        prix: p.price_xof,
        image: p.image,
        accent: p.accent,
        index: sansAccents([p.brand, p.model, p.colorway, p.category, p.gender, p.sku ?? ''].join(' ')),
      })),
      ...jerseys.map((j) => ({
        cle: `jersey-${j.id}`,
        to: `/maillots/${j.slug}`,
        titre: j.club,
        detail: `${j.kit} · ${j.season}`,
        prix: j.price_xof,
        image: j.image,
        accent: j.accent,
        index: sansAccents([j.club, j.league, j.brand, j.kit, j.season, j.colorway].join(' ')),
      })),
    ],
    [products, jerseys],
  );

  // Chaque mot doit se retrouver, dans n'importe quel ordre : « nike 95 »
  // trouve l'Air Max 95 aussi bien que « 95 nike ».
  const resultats = useMemo(() => {
    const mots = sansAccents(terme).split(/\s+/).filter(Boolean);

    if (mots.length === 0) return [];

    return entrees.filter((entree) => mots.every((mot) => entree.index.includes(mot))).slice(0, 8);
  }, [entrees, terme]);

  // Le champ prend le curseur à l'ouverture : on a cliqué pour taper.
  useEffect(() => {
    if (open) {
      setTerme('');
      const id = window.setTimeout(() => champ.current?.focus(), 60);

      return () => window.clearTimeout(id);
    }
  }, [open]);

  // Échap ferme, comme partout ailleurs.
  useEffect(() => {
    if (!open) return;

    const auClavier = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose();
    };

    window.addEventListener('keydown', auClavier);

    return () => window.removeEventListener('keydown', auClavier);
  }, [open, onClose]);

  if (!open) return null;

  const ouvrir = (to: string) => {
    onClose();
    navigate(to);
  };

  return (
    <div
      className="fixed inset-0 z-[80] flex justify-center px-4 pt-[14vh] sm:pt-[16vh]"
      role="dialog"
      aria-modal="true"
      aria-label="Recherche"
    >
      <div onClick={onClose} className="absolute inset-0 bg-black/70 backdrop-blur-sm" />

      <div className="relative w-full max-w-xl">
        <div className="flex items-center gap-3 border border-white/15 bg-[#0B0C0E] px-4 py-3.5">
          <Search className="h-4 w-4 shrink-0 text-white/40" strokeWidth={2.2} />
          <input
            ref={champ}
            value={terme}
            onChange={(event) => setTerme(event.target.value)}
            onKeyDown={(event) => {
              // Entrée ouvre le premier résultat : le geste attendu quand on a
              // tapé trois lettres et qu'on voit déjà ce qu'on cherchait.
              if (event.key === 'Enter' && resultats[0]) ouvrir(resultats[0].to);
            }}
            type="search"
            placeholder="Une paire, un club, un coloris…"
            aria-label="Rechercher un article"
            className="min-w-0 flex-1 bg-transparent text-[13px] text-white placeholder:text-white/30 focus:outline-none"
          />
          <button
            type="button"
            onClick={onClose}
            aria-label="Fermer la recherche"
            className="shrink-0 text-white/40 transition-colors hover:text-white"
          >
            <X className="h-4 w-4" strokeWidth={2.2} />
          </button>
        </div>

        {terme.trim() !== '' && (
          <div className="mt-2 max-h-[52vh] overflow-y-auto border border-white/15 bg-[#0B0C0E]">
            {resultats.length === 0 ? (
              <p className="px-4 py-8 text-center text-[11px] leading-[1.7] text-white/40">
                Rien pour « {terme.trim()} ».
                <br />
                Essayez une marque, un club ou une couleur.
              </p>
            ) : (
              <ul>
                {resultats.map((resultat) => (
                  <li key={resultat.cle}>
                    <button
                      type="button"
                      onClick={() => ouvrir(resultat.to)}
                      className="flex w-full items-center gap-4 border-b border-white/[0.06] px-4 py-3 text-left transition-colors last:border-0 hover:bg-white/[0.05]"
                    >
                      <span
                        className="relative flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden border border-white/10"
                        style={{
                          background: `radial-gradient(ellipse 80% 70% at 50% 56%, ${resultat.accent}8C 0%, ${resultat.accent}26 60%, transparent 82%)`,
                        }}
                      >
                        {resultat.image && (
                          <img src={resultat.image} alt="" loading="lazy" className="h-full w-full object-contain p-0.5" />
                        )}
                      </span>

                      <span className="min-w-0 flex-1">
                        <span className="block truncate text-[11px] font-bold uppercase tracking-[0.06em] text-[#EDEFF2]">
                          {resultat.titre}
                        </span>
                        <span className="mt-0.5 block truncate text-[10px] text-white/40">{resultat.detail}</span>
                      </span>

                      <span className="shrink-0 font-display text-[12px] text-[#EDEFF2]">{formatXof(resultat.prix)}</span>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
