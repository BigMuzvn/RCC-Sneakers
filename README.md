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
| `/compte` | Connexion et inscription sur une page, avec interrupteur |
| `/checkout` | Coordonnées, livraison, paiement, récapitulatif |
| `/contact` | Formulaire et coordonnées |
| `/mentions-legales` `/cgv` `/confidentialite` `/cookies` `/livraison-retours` `/authenticite` | Pages légales, gabarit commun |

L'accueil tient en un écran sans défilement (`100svh`) et n'a donc **pas de footer** ; toutes les autres pages en ont un.

## Panier

Le panier vit dans un contexte React (`src/context/`) et **persiste dans le navigateur**. Il ne stocke que `{ type, id, size, qty }` puis reconstruit les lignes depuis le catalogue au chargement : les URL d'images sont des empreintes de build qui changent à chaque compilation, un panier stocké en dur casserait au premier redéploiement.

Il gère indifféremment les sneakers (tailles 39-45) et les maillots (S-XXL). L'ajout se fait depuis les cartes via un panneau de tailles superposé, sans quitter la page, ou depuis la fiche produit. Un ajout n'ouvre **pas** le tiroir — il déclenche une notification, pour qu'on puisse enchaîner plusieurs articles.

Le fichier est scindé en deux : `cart-context.ts` porte le contexte, les types et le hook, `CartContext.tsx` ne contient que le composant fournisseur. C'est ce qui évite l'avertissement de rafraîchissement à chaud de React.

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

**Fait** : les 11 routes, le responsive (vérifié de 360 à 1400 px, sans débordement horizontal ni vertical), les filtres et tris, le sélecteur de tailles avec stock, le panier complet avec persistance, le tunnel de commande, le menu mobile, le footer et sa lettre d'information.

### La prochaine étape : le backend

Le front est en avance sur le back, et **certaines pages ne peuvent pas être écrites avant lui**. En particulier, l'espace client (commandes, adresses, favoris) suppose une authentification réelle — il faut donc commencer par là.

Ordre suggéré :

1. **Authentification** — `POST /api/auth/register`, `POST /api/auth/login`, session ou jeton. Débloque l'espace client.
2. **Commandes** — `POST /api/orders`, le manque le plus critique aujourd'hui.
3. **Catalogue** — `GET /api/products`, `GET /api/jerseys`. Les modules de `src/data/` sont déjà à la forme attendue.
4. **Contact et lettre d'information** — `POST /api/contact`, `POST /api/newsletter`.

### Ce qui n'est pas fonctionnel

Chaque point ci-dessous porte un `TODO` à l'endroit exact dans le code.

| Manque | Détail |
|---|---|
| Commandes | Le tunnel va jusqu'au bout et affiche une référence, mais **rien n'est enregistré** et la référence est générée côté navigateur. À câbler avant toute mise en ligne. |
| Authentification | Les formulaires valident et affichent une confirmation, mais **aucune session n'est créée**. |
| Formulaire de contact | Valide et confirme, **n'envoie rien**. |
| Lettre d'information | Valide et confirme, **n'enregistre rien**. |
| Espace client | Pas encore commencé — dépend de l'authentification. |
| Fiche maillot | Les cartes maillots ne mènent nulle part, il n'y a pas de page de détail. |
| Visuels produits | 16 sneakers sur 20 et les 12 maillots n'ont pas de rendu. Les cartes basculent sur un halo dans la couleur du coloris avec « visuel à venir ». Déposer le PNG et remplacer `image: null` par l'import suffit. |
| Informations légales | Tout ce qui est entre crochets dans `data/legal.ts` : RCCM, IFU, hébergeur, numéro APDP. Ce sont des identifiants officiels, ils n'ont pas été inventés. |
| Tarifs de livraison | 1 000 / 1 500 / 2 500 F CFA sont des valeurs de remplacement, en haut de `Checkout.tsx`. |
| Coordonnées | Téléphone, e-mail et liens réseaux sont des valeurs de remplacement, en haut de `Footer.tsx` et `Contact.tsx`. |
