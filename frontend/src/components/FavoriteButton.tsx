import { useNavigate } from 'react-router-dom';
import { Heart } from 'lucide-react';
import { useAuth } from '../context/auth-context';
import { useFavorites, type FavoriteType } from '../context/favorites-context';

type Props = {
  type: FavoriteType;
  id: number;
  label: string;
};

/**
 * Bouton cœur posé sur une carte produit.
 *
 * `z-20` et `stopPropagation` : la carte est un article avec un lien étiré en
 * `absolute inset-0`, donc sans cela le clic partirait vers la fiche produit au
 * lieu de basculer le favori.
 */
export default function FavoriteButton({ type, id, label }: Props) {
  const navigate = useNavigate();
  const { customer } = useAuth();
  const { isFavorite, toggle } = useFavorites();

  const active = isFavorite(type, id);

  return (
    <button
      type="button"
      aria-pressed={active}
      aria-label={active ? `Retirer ${label} des favoris` : `Ajouter ${label} aux favoris`}
      onClick={(event) => {
        event.preventDefault();
        event.stopPropagation();

        // Les favoris vivent sur le compte : sans session il n'y a nulle part
        // où les écrire. On emmène le visiteur s'inscrire plutôt que de laisser
        // le cœur se remplir puis se vider tout seul.
        if (!customer) {
          navigate('/compte?retour=favori');
          return;
        }

        void toggle(type, id);
      }}
      className={`absolute right-2.5 top-2.5 z-20 flex h-8 w-8 items-center justify-center rounded-full border backdrop-blur-sm transition-colors duration-200 motion-reduce:transition-none ${
        active
          ? 'border-white/40 bg-[#EDEFF2] text-[#17191C]'
          : 'border-white/20 bg-[#08090B]/70 text-white/70 hover:border-white/45 hover:text-white'
      }`}
    >
      <Heart className="h-[15px] w-[15px]" strokeWidth={2.2} fill={active ? 'currentColor' : 'none'} />
    </button>
  );
}
