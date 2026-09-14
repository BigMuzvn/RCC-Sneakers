# Comment on en est arrivé là

Journal des décisions de conception. À lire avant de modifier la direction artistique ou le modèle de données — plusieurs choix qui paraissent arbitraires répondent à une contrainte précise.

---

## 1. Le point de départ

Le projet contenait un premier essai (catalogue sombre et doré, panier, checkout mobile money, API PHP). Il a été **entièrement vidé** pour repartir de la maquette [`reference-maquette-hero.jpeg`](reference-maquette-hero.jpeg) : une page produit plein écran, mot géant en filigrane, chaussure détourée au centre, sélecteur de coloris et double bouton d'achat.

Seules les dépendances installées ont été conservées. Poppins, Archivo Black et lucide-react ont été ajoutés pour coller à la maquette.

**Deux écarts volontaires avec la maquette :** les logos Jordan et Nike n'ont pas été repris comme identité du site, et le nom de produit « Jordan Jumpman » non plus. Ce sont des marques déposées, et les placer en en-tête d'une vraie boutique laisserait entendre une affiliation. Le logo RCC les remplace. En revanche, **nommer les produits réellement vendus** (« Nike P-6000 ») est un usage légitime de revendeur — c'est pourquoi les fiches produits, elles, portent les vrais noms.

## 2. Le principe qui tient tout le site

L'accueil change la couleur de son fond à chaque paire du carrousel. C'est ce que le client remarque en premier, et c'est devenu la règle de tout le site.

**La couleur n'est jamais choisie à l'œil.** Pour chaque visuel, les tons dominants sont extraits de l'image :

```python
from PIL import Image
from collections import Counter
im = Image.open('image.png').convert('RGBA')
small = im.resize((180, 245))
px = [p[:3] for p in small.get_flattened_data() if p[3] > 200]
print(Counter(tuple(v // 24 * 24 for v in p) for p in px).most_common(8))
```

Les valeurs obtenues alimentent le dégradé. Exemples : `#F0481B` (boîte Nike orange) et `#C8142E` pour la boutique, `#483000` / `#906030` (semelle gum) pour le contact, `#184848` (vert collégial) pour les soldes, `#001848` / `#900018` (blaugrana) pour les maillots.

Sur les pages internes, le dispositif est une **paire suspendue par ses lacets** qui tombe du haut, dont le coloris éclaire la bande supérieure. Deux calques : un lavis large en `linear-gradient`, puis un halo `radial-gradient` centré sur la paire. Ils défilent avec elle, donc la page s'assombrit vers la grille et les cartes gardent leur contraste.

Sur la page maillots, pas de suspension : le visuel est posé **face au titre**.

### Deux pièges rencontrés

**Z-index négatif.** Les calques décoratifs placés en `-z-10` étaient invisibles : un enfant à z-index négatif est peint *derrière le fond opaque de son conteneur*. Solution : aucun z-index négatif. Le dégradé de base et les décors sont en `z-0`, le contenu en `z-10`, et c'est le calque de dégradé qui porte la couleur de fond, pas le conteneur de page.

**Position mobile.** L'espace libre dans la barre de navigation est à droite sur desktop (entre le dernier lien et les icônes) mais **entre le logo et les icônes** sur téléphone. La paire suspendue change donc de position au point de rupture, et le centre du halo la suit via une variable CSS `--glow-x`.

## 3. Le catalogue

L'intention initiale était d'alimenter la boutique via l'API **KicksDB**. Elle exige une clé :

```
GET https://api.kicks.dev/v3/stockx/products?query=Nike Air Max
→ 401 {"title":"Unauthorized","detail":"Invalid key"}
```

Deux alternatives testées (`sneakersapi.dev`, `thesneakerdatabase`) : clé requise ou service injoignable.

Le catalogue a donc été constitué par **recherche vérifiée**. Ce n'est pas une perte : l'API n'aurait fourni que quatre champs utiles (nom, coloris, SKU, tarif public). Les prix marketplace sont de toute façon remplacés par des prix en F CFA, et les images par des rendus maison.

**Le vrai facteur limitant, ce sont les images, pas les données.** Le catalogue fera toujours la taille du nombre de rendus produits disponibles. D'où le choix de 20 sneakers plutôt que 40, et d'un remplacement graphique assumé pour les visuels manquants — un halo dans la couleur réelle du coloris, qui se lit comme un parti pris et non comme une image cassée. Sur la page soldes, on distingue au premier coup d'œil une Air Max 90 Infrared d'une Jordan 11 Concord sans qu'aucune photo n'existe.

## 4. Alignements

Les titres des pages internes sont **calés au pixel près** : `h1` à 167 px du haut en 1400 et 1280 px, 122 px en 390 px.

La page maillots a cassé cet alignement deux fois. D'abord parce que son titre était dans une grille à deux colonnes en `items-center` : le bloc texte se centrait verticalement contre la colonne du maillot, bien plus haute, ce qui le poussait 99 px plus bas. Puis parce que la hauteur minimale de l'en-tête laissait 262 px de vide avant les filtres.

Le titre a donc été **sorti de la grille** pour reprendre la structure exacte des autres pages, et le maillot positionné en absolu à côté de lui.

À retenir : c'est le **bas** de ce visuel qui repousse tout le reste. Pour l'agrandir, on le remonte plutôt que de l'étaler — il est calé à 95 px du haut, soit 27 px sous la barre de navigation. Ses marges transparentes ont aussi été détourées, ce qui donne 5 % de taille utile en plus à encombrement égal.

## 5. Méthode de travail

Chaque changement d'interface est **vérifié dans un vrai navigateur** avant d'être annoncé : Playwright, captures en 1400 / 1280 / 768 / 390 / 375 / 360 px, et contrôle programmatique de l'absence de débordement horizontal et vertical.

Ce sont ces passages qui ont révélé la paire suspendue totalement invisible, le swoosh noir sur fond noir, la boîte à chaussures collée aux icônes mobiles, l'encart de statistiques débordant de son cadre, et les décalages de titre ci-dessus. Aucun de ces défauts n'était visible dans le code.
