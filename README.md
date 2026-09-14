# RCC Sneakers

Front-end d'une boutique de sneakers et de maillots basée à **Cotonou, Bénin**. Prix en F CFA, interface en français.

Le site est aujourd'hui une vitrine complète mais **sans backend** : toutes les données viennent de modules locaux dont la forme reproduit déjà celle de la future API PHP.

---

## Démarrer

```bash
cd frontend
npm install
npm run dev      # http://localhost:5173
```

| Commande | Rôle |
|---|---|
| `npm run dev` | serveur de développement |
| `npm run build` | build de production (`tsc -b` puis Vite) |
| `npm run lint` | oxlint |
| `npm run preview` | prévisualise le build |

## Stack

- **React 19** + **TypeScript** + **Vite 8**
- **Tailwind CSS v3** (v4 a été abandonnée, son plugin PostCSS posait problème)
- **react-router-dom** pour la navigation
- **lucide-react** pour les icônes — attention, la v1 a retiré les icônes de marque (`Instagram`, `Facebook` n'existent plus)
- **@fontsource/poppins** et **@fontsource/archivo-black**, auto-hébergées
- Backend **PHP + MySQL** prévu, pas encore commencé (`backend/` est vide)

## Pages

| Route | Contenu |
|---|---|
| `/` | Accueil — carrousel plein écran, 4 paires, défilement auto 5 s |
| `/boutique` | Catalogue 20 sneakers, filtres marque et catégorie |
| `/boutique/:slug` | Fiche produit — tailles, stock, suggestions |
| `/maillots` | 12 maillots de clubs et sélections, filtre par championnat |
| `/soldes` | 8 modèles en remise, 3 tris |
| `/contact` | Formulaire et coordonnées |

L'accueil tient en un écran sans défilement (`100svh`) et n'a donc **pas de footer** ; toutes les autres pages en ont un.

## Structure

```
frontend/src/
├── components/
│   ├── Hero.tsx            accueil, carrousel et fond par slide
│   ├── Navbar.tsx          nav partagée + menu burger mobile
│   ├── Footer.tsx          footer partagé
│   ├── HangingShoe.tsx     paire suspendue + halo (props : image, primary, secondary)
│   ├── Boutique.tsx  Soldes.tsx  Maillots.tsx  Contact.tsx
│   ├── ProductDetail.tsx   fiche sneaker
│   ├── ProductCard.tsx  JerseyCard.tsx  Chip.tsx
├── data/
│   ├── products.ts         20 sneakers
│   └── jerseys.ts          12 maillots
└── utils/format.ts         formatage F CFA
```

## Données

Les champs sont nommés **en `snake_case`**, exactement comme les renverra `GET /api/products`. Le jour où le backend existe, on remplace l'import par un `fetch` sans toucher aux composants ni écrire de couche de correspondance.

Le stock est modélisé **par taille** (`variants: [{ size, stock }]`), ce qui correspond à une table `product_variants` en base et permet à l'interface de griser les tailles épuisées.

**Prix** : convertis depuis le tarif public en dollars au taux réel du 14/09/2026 (**1 USD = 565,65 F CFA**, relevé sur `open.er-api.com`, cohérent avec la parité fixe EUR/XOF à 655,957), puis arrondis aux 500 F. Aucune marge revendeur n'est appliquée — à trancher.

**Références (SKU)** : renseignées uniquement quand elles sont vérifiables, `null` sinon. Un faux SKU devant un client vaut moins que pas de SKU.

## Direction artistique

Tout part de la maquette d'origine : [`docs/reference-maquette-hero.jpeg`](docs/reference-maquette-hero.jpeg).

- **Typographie** — Archivo Black pour les grands titres et les prix, Poppins pour le reste. Capitales très espacées (`tracking`) sur les petits libellés.
- **Le principe central** : *le fond prend la couleur du produit*. L'accueil change de dégradé à chaque paire du carrousel ; les pages internes reçoivent la même idée via une paire suspendue par ses lacets dont le coloris éclaire le haut de page.
- Chaque page a sa dominante : rouge en boutique, ambre en contact, vert en soldes, bleu-cramoisi en maillots.
- Les couleurs ne sont jamais choisies à l'œil : elles sont **échantillonnées dans l'image** (voir [`docs/CONCEPTION.md`](docs/CONCEPTION.md)).

## État actuel

**Fait** : les 6 pages, le responsive (vérifié de 360 à 1400 px, sans débordement), les filtres et tris, le sélecteur de tailles avec stock, le menu mobile, le footer.

**Pas fait, volontairement :**

| Manque | Détail |
|---|---|
| Panier | Les boutons « Ajouter au panier » sont inertes. Choix assumé : mieux vaut un bouton inerte qu'un faux « ajouté ✓ » menant à un panier vide. |
| Formulaire de contact | Valide les champs et affiche une confirmation, mais **n'envoie rien**. À câbler sur `POST /api/contact` avant toute mise en ligne. |
| Fiche maillot | Les cartes maillots ne sont pas cliquables, il n'y a pas encore de page de détail. |
| Visuels produits | 16 sneakers sur 20 et les 12 maillots n'ont pas de rendu. Les cartes basculent alors sur un halo dans la couleur du coloris avec « visuel à venir ». Déposer le PNG et remplacer `image: null` par l'import suffit. |
| Coordonnées | Téléphone, e-mail et liens réseaux sont des valeurs de remplacement, centralisées en haut de `Footer.tsx` et `Contact.tsx`. |
