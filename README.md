# RCC Sneakers

Front-end d'une boutique de sneakers et de maillots basée à **Cotonou, Bénin**. Prix en F CFA, interface en français.

Le catalogue est une vitrine complète servie par des modules locaux. Le backend a démarré : **l'authentification est faite** — inscription, connexion par e-mail ou téléphone, sessions, vérification d'adresse et réinitialisation de mot de passe.

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
- Backend **PHP 8 natif + MySQL**, sans aucune dépendance en production
- **Brevo** pour l'e-mail transactionnel

## Pages

| Route | Contenu |
|---|---|
| `/` | Accueil — carrousel plein écran, 4 paires, défilement auto 5 s |
| `/boutique` | Catalogue 20 sneakers, filtres marque et catégorie |
| `/boutique/:slug` | Fiche produit — tailles, stock, suggestions |
| `/maillots` | 12 maillots de clubs et sélections, filtre par championnat |
| `/soldes` | 8 modèles en remise, 3 tris |
| `/compte` | Connexion et inscription sur une page, avec interrupteur |
| `/compte/verifier` | Atterrissage du lien de vérification reçu par e-mail |
| `/compte/mot-de-passe-oublie` | Demande d'un lien de réinitialisation |
| `/compte/reinitialiser` | Atterrissage du lien de réinitialisation |
| `/espace-client` | Commandes, informations, favoris — session requise |
| `/checkout` | Coordonnées, livraison, paiement, récapitulatif |
| `/contact` | Formulaire et coordonnées |
| `/mentions-legales` `/cgv` `/confidentialite` `/cookies` `/livraison-retours` `/authenticite` | Pages légales, gabarit commun |

L'accueil tient en un écran sans défilement (`100svh`) et n'a donc **pas de footer** ; toutes les autres pages en ont un.

## Panier

Le panier vit dans un contexte React (`src/context/`) et **persiste dans le navigateur**. Il ne stocke que `{ type, id, size, qty }` puis reconstruit les lignes depuis le catalogue au chargement : les URL d'images sont des empreintes de build qui changent à chaque compilation, un panier stocké en dur casserait au premier redéploiement.

Il gère indifféremment les sneakers (tailles 39-45) et les maillots (S-XXL). L'ajout se fait depuis les cartes via un panneau de tailles superposé, sans quitter la page, ou depuis la fiche produit. Un ajout n'ouvre **pas** le tiroir — il déclenche une notification, pour qu'on puisse enchaîner plusieurs articles.

Le fichier est scindé en deux : `cart-context.ts` porte le contexte, les types et le hook, `CartContext.tsx` ne contient que le composant fournisseur. C'est ce qui évite l'avertissement de rafraîchissement à chaud de React.

## Backend

```bash
cd backend
cp config.example.php config.php    # puis renseigner base et clé Brevo
composer install                    # PHPUnit seulement, en développement
php migrations/run.php              # crée le schéma
php migrations/run.php --test       # base de test
php -S localhost:8000 -t public     # l'API
./vendor/bin/phpunit                # 130 tests
```

Vite proxie `/api` vers le port 8000 : le navigateur ne voit qu'une origine, donc le cookie de session se comporte en développement comme en production.

### Pourquoi du PHP natif

**Aucune dépendance ne part en production.** `vendor/` ne contient que PHPUnit, et l'autoloader est huit lignes de PSR-4 dans `backend/autoload.php`. L'API se déploie par FTP et tourne sur un mutualisé à quelques milliers de francs par mois — c'est un argument commercial autant qu'une contrainte.

Le SQL est délibérément conservateur : développement en MySQL 8.4, production probable en 5.7 ou MariaDB 10.x. Pas de CTE, pas de `CHECK` (silencieusement ignoré en 5.7), collation `utf8mb4_unicode_ci`, colonnes indexées en `VARCHAR(191)` — en utf8mb4 un index sur 255 caractères pèse 1020 octets et dépasse la limite de 767 des anciens InnoDB.

Le hachage est **bcrypt explicite**, pas Argon2id. Argon2id existe en local, mais beaucoup de mutualisés compilent PHP sans sodium : un hachage que l'hébergeur ne sait pas relire enfermerait tous les clients dehors.

### Routes d'authentification

| Route | Rôle |
|---|---|
| `POST /api/auth/register` | crée le compte, connecte, envoie le lien de vérification |
| `POST /api/auth/login` | `identifier` = **e-mail ou téléphone**, `password`, `remember` |
| `POST /api/auth/logout` | révoque le jeton de cette session uniquement |
| `GET /api/auth/me` | client courant, ou 401 |
| `POST /api/auth/verify-email` | consomme le jeton reçu par mail |
| `POST /api/auth/resend-verification` | |
| `POST /api/auth/forgot-password` | |
| `POST /api/auth/reset-password` | |
| `POST /api/auth/profile` | nom, e-mail, téléphone |
| `POST /api/auth/password` | exige le mot de passe actuel |
| `GET /api/favorites` | |
| `POST /api/favorites/toggle` | `item_type` + `item_id` |

Enveloppe constante : `{ "data": … }` ou `{ "error": { code, message, fields } }`, messages en français, champs en `snake_case`.

### Sessions

Pas de sessions natives PHP. Sur un mutualisé, leurs fichiers atterrissent souvent dans un répertoire temporaire partagé entre comptes. Un jeton en base couvre la session **et** le « se souvenir de moi » — seule la durée change — survit à un changement de serveur, et se révoque réellement côté serveur.

Le cookie porte `sélecteur.validateur` ; la base ne garde que le sélecteur et le **SHA-256** du validateur. Une fuite de la table ne donne aucune session utilisable. Comparaison par `hash_equals`, pour que la durée de réponse ne laisse pas deviner le jeton.

### Ce qui n'est pas négociable dans ce code

**Aucun oracle d'énumération.** Mot de passe faux et compte inconnu renvoient le même statut, le même message et la même durée — un `password_verify` factice est exécuté quand aucun compte n'est trouvé. `forgot-password` répond identiquement dans tous les cas. Sans cela ces formulaires diraient à n'importe qui qui est client de la boutique.

**Limitation de débit sur les routes d'envoi.** Le forfait Brevo est à **300 e-mails par jour**. Sans plafond, marteler `forgot-password` l'épuise en une minute et coupe tous les e-mails de la boutique, confirmations de commande comprises, jusqu'au lendemain — une panne totale déclenchable depuis un navigateur.

**Une réinitialisation révoque toutes les sessions**, sinon une session détournée survit au changement de mot de passe.

**Un envoi raté ne fait jamais échouer une inscription.** Le compte est créé, l'échec journalisé. L'inverse ferme la boutique à chaque panne de Brevo.

## Espace client

Trois volets : **commandes**, **informations**, **favoris**.

**Un compte non vérifié n'est jamais bloquant.** Beaucoup de clients s'inscriront avec une adresse secondaire, et exiger un clic dans un e-mail peut-être classé en indésirables refoulerait de vrais acheteurs. Un bandeau rappelle le risque concret — sans adresse confirmée, aucun lien de réinitialisation ne peut parvenir — mais l'accès reste entier.

**Ce qui est bloquant, c'est de commander sans compte.** L'ajout au panier reste libre ; c'est au moment de payer que la question se pose. Le tiroir ouvre alors une confirmation plutôt qu'une redirection sèche : une redirection au moment de payer se lit comme un mur, et le client craint d'avoir perdu son panier. La fenêtre dit explicitement qu'il est conservé, et propose inscription ou connexion. `/checkout` porte la même garde, car l'adresse reste atteignable par signet ou après expiration de session.

**Les favoris ne stockent que des identifiants**, comme le panier et pour la même raison. Ils sont rattachés au compte, donc chargés à la connexion et effacés à la déconnexion — sur un appareil partagé, cas courant ici, ceux d'un client ne doivent pas rester affichés au suivant. La bascule est optimiste : le cœur réagit au doigt, pas à la latence, et revient en arrière si l'appel échoue.

**Changer d'adresse annule la vérification** et déclenche un nouvel envoi. Sans cela, on pourrait inscrire une adresse qu'on contrôle, la faire vérifier, puis la remplacer par celle d'un tiers en gardant la pastille « vérifié ».

**Changer de mot de passe déconnecte les autres appareils mais pas le sien.** Être éjecté juste après avoir validé son propre formulaire serait incompréhensible.

### Le visuel latéral

`SideShoe` est la variante latérale de la paire suspendue : le visuel entre par le bord droit et le halo suit cet axe, ancré à droite plutôt qu'en haut. Les tons viennent de l'image — périwinkle 30 %, bordeaux-rose 23 %, le crème étant majoritaire mais sans caractère.

Le contenu reçoit une **gouttière à droite** calée sur la largeur de la paire à chaque palier. Sur téléphone la paire est masquée et seul le halo demeure : une paire entrant par la droite a besoin de cette gouttière, et sur 390 px elle prendrait la moitié de l'écran — mesuré, elle passait en travers du bandeau qui devenait illisible.

## Structure

```
backend/
├── autoload.php            PSR-4 maison, pour se passer de Composer en production
├── public/index.php        contrôleur frontal — seul fichier exposé
├── src/
│   ├── App.php             routes et assemblage, partagé par index.php et les tests
│   ├── Auth.php            sessions par jeton en base
│   ├── CustomerToken.php   jetons e-mail à usage unique
│   ├── RateLimiter.php  CookieJar.php  Validator.php  Phone.php
│   ├── Mailer/             interface + BrevoMailer + LogMailer
│   └── Controllers/  AuthController.php  FavoritesController.php
├── migrations/   001_auth.sql  002_favorites.sql
└── tests/                  130 tests

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

### La prochaine étape

1. ~~**Authentification**~~ — faite. 12 routes, 130 tests.
2. ~~**Pages client**~~ — faites. Espace client à trois volets, pages des liens e-mail, favoris, verrou avant paiement.
3. **Commandes** — `POST /api/orders`. C'est maintenant le seul manque structurel : le tunnel affiche une référence que rien n'enregistre, et le volet « Commandes » n'a donc aucune donnée à montrer.
4. **Catalogue** — `GET /api/products`, `GET /api/jerseys`. Les modules de `src/data/` sont déjà à la forme attendue.
5. **Contact et lettre d'information** — `POST /api/contact`, `POST /api/newsletter`.

### Ce qui n'est pas fonctionnel

Chaque point ci-dessous porte un `TODO` à l'endroit exact dans le code.

| Manque | Détail |
|---|---|
| Commandes | Le tunnel va jusqu'au bout et affiche une référence, mais **rien n'est enregistré** et la référence est générée côté navigateur. À câbler avant toute mise en ligne. |
| Suivi de commande | Le volet existe mais reste vide : aucune commande n'est enregistrée. Un aperçu de maquette, signalé comme tel par un cadre pointillé, montre la forme prévue. |
| Téléchargement de facture | Bouton présent et désactivé, dans l'aperçu. Rien à générer tant que les commandes ne sont pas persistées. |
| Formulaire de contact | Valide et confirme, **n'envoie rien**. |
| Lettre d'information | Valide et confirme, **n'enregistre rien**. |
| Domaine vérifié dans Brevo | Le seul expéditeur vérifié est une adresse `@gmail.com`. `gmail.com` publie `p=none` donc rien n'est rejeté, mais son SPF (`redirect=_spf.google.com`) n'inclut pas Brevo : tout envoi échoue SPF et l'alignement DKIM, ce que Gmail et Outlook pèsent lourdement en score anti-spam. Un mail de réinitialisation en indésirables est une fonctionnalité morte. |
| Expéditeur Brevo | S'appelle encore « ProductHunt Lite ». À renommer dans leur interface. |
| Clé d'API Brevo | Transmise en clair pendant le développement : à régénérer avant la mise en ligne. |
| Fiche maillot | Les cartes maillots ne mènent nulle part, il n'y a pas de page de détail. |
| Visuels produits | 16 sneakers sur 20 et les 12 maillots n'ont pas de rendu. Les cartes basculent sur un halo dans la couleur du coloris avec « visuel à venir ». Déposer le PNG et remplacer `image: null` par l'import suffit. |
| Informations légales | Tout ce qui est entre crochets dans `data/legal.ts` : RCCM, IFU, hébergeur, numéro APDP. Ce sont des identifiants officiels, ils n'ont pas été inventés. |
| Tarifs de livraison | 1 000 / 1 500 / 2 500 F CFA sont des valeurs de remplacement, en haut de `Checkout.tsx`. |
| Coordonnées | Téléphone, e-mail et liens réseaux sont des valeurs de remplacement, en haut de `Footer.tsx` et `Contact.tsx`. |
