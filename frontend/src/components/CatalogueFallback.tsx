import { useCatalogue } from '../context/catalogue-context';

/**
 * Ce qui s'affiche à la place d'une grille tant que le catalogue n'est pas là.
 *
 * Trois situations bien distinctes, et qui ne se disent pas de la même façon :
 * la réponse arrive, la réponse n'est pas venue, ou elle est venue vide. Un
 * seul « aucun article » pour les trois ferait croire à une boutique déserte
 * alors que c'est le réseau qui a coupé — et le visiteur repartirait.
 */
export default function CatalogueFallback({ vide }: { vide: string }) {
  const { loading, error, reload } = useCatalogue();

  if (loading) {
    return (
      <div className="mt-4 border border-white/10 bg-white/[0.02] px-6 py-16 text-center">
        <p className="text-[11px] uppercase tracking-[0.2em] text-white/40">Chargement du catalogue…</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="mt-4 border border-[#E2564A]/30 bg-[#E2564A]/[0.06] px-6 py-16 text-center">
        <p className="text-sm font-bold uppercase tracking-[0.12em] text-[#EDEFF2]">Catalogue indisponible</p>
        <p className="mx-auto mt-2 max-w-md text-xs leading-[1.7] text-white/55">{error}</p>
        <button
          type="button"
          onClick={reload}
          className="mt-5 border border-white px-6 py-3 text-[11px] font-bold uppercase tracking-[0.12em] text-white transition-colors hover:bg-white hover:text-[#141516]"
        >
          Réessayer
        </button>
      </div>
    );
  }

  return (
    <div className="mt-4 border border-white/10 bg-white/[0.02] px-6 py-16 text-center">
      <p className="text-sm font-bold uppercase tracking-[0.12em] text-[#EDEFF2]">Aucun modèle</p>
      <p className="mt-2 text-xs text-white/50">{vide}</p>
    </div>
  );
}
