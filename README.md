# RCC Sneakers

Front-end d'une boutique de sneakers et de maillots basée à **Cotonou, Bénin**. Prix en F CFA, interface en français.

Le catalogue est une vitrine complète servie par des modules locaux. Le backend a démarré : **l'authentification est faite** — inscription, connexion par e-mail ou téléphone, sessions, vérification d'adresse et réinitialisation de mot de passe.

---

## Démarrer

**Deux serveurs, deux terminaux.** Lancer le front seul donne un site qui s'affiche mais dont aucun formulaire ne fonctionne : Vite proxie `/api` vers le port 8000, et si rien n'y répond il renvoie un 502 au lieu d'une réponse de l'API.

```bash
# terminal 1 — l'API
cd backend
php -S localhost:8000 -t public

# terminal 2 — le front
cd frontend
npm run dev      # http://localhost:5173
```

Première fois, voir [Backend](#backend) pour `config.php` et les migrations.

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
| `/` | Accueil — carrousel plein écran, 4 paires du catalogue, achat direct, défilement auto 5 s |
| `/boutique` | Catalogue 20 sneakers, filtres marque et catégorie |
| `/boutique/:slug` | Fiche produit — tailles, stock, suggestions |
| `/maillots` | 12 maillots de clubs et sélections, filtre par championnat |
| `/maillots/:slug` | Fiche maillot — tailles, stock, flocage, suggestions |
| `/soldes` | 8 modèles en remise, 3 tris |
| `/compte` | Connexion et inscription sur une page, avec interrupteur |
| `/compte/verifier` | Atterrissage du lien de vérification reçu par e-mail |
| `/compte/mot-de-passe-oublie` | Demande d'un lien de réinitialisation |
| `/compte/reinitialiser` | Atterrissage du lien de réinitialisation |
| `/espace-client` | Commandes, informations, favoris — session requise |
| `/checkout` | Coordonnées, livraison, paiement, récapitulatif |
| `/admin` | Administration — session administrateur requise |
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
php migrations/seed.php             # charge le catalogue
php bin/visuels.php                 # importe les rendus du dépôt dans les visuels
php migrations/run.php --test       # base de test
php migrations/seed.php --test      # catalogue de test
php -S localhost:8000 -t public     # l'API
php bin/admin.php promouvoir <e-mail>   # premier administrateur
./vendor/bin/phpunit                # 262 tests
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
| `GET /api/products` `GET /api/jerseys` | catalogue, forme identique aux modules `src/data/` |
| `GET /api/delivery-zones` | zones et tarifs |
| `POST /api/orders` | passe la commande |
| `GET /api/orders` | ses commandes |
| `GET /api/orders/{reference}` | une commande |

Enveloppe constante : `{ "data": … }` ou `{ "error": { code, message, fields } }`, messages en français, champs en `snake_case`.

### Sessions

Pas de sessions natives PHP. Sur un mutualisé, leurs fichiers atterrissent souvent dans un répertoire temporaire partagé entre comptes. Un jeton en base couvre la session **et** le « se souvenir de moi » — seule la durée change — survit à un changement de serveur, et se révoque réellement côté serveur.

Le cookie porte `sélecteur.validateur` ; la base ne garde que le sélecteur et le **SHA-256** du validateur. Une fuite de la table ne donne aucune session utilisable. Comparaison par `hash_equals`, pour que la durée de réponse ne laisse pas deviner le jeton.

### Ce qui n'est pas négociable dans ce code

**Aucun oracle d'énumération.** Mot de passe faux et compte inconnu renvoient le même statut, le même message et la même durée — un `password_verify` factice est exécuté quand aucun compte n'est trouvé. `forgot-password` répond identiquement dans tous les cas. Sans cela ces formulaires diraient à n'importe qui qui est client de la boutique.

**Limitation de débit sur les routes d'envoi.** Le forfait Brevo est à **300 e-mails par jour**. Sans plafond, marteler `forgot-password` l'épuise en une minute et coupe tous les e-mails de la boutique, confirmations de commande comprises, jusqu'au lendemain — une panne totale déclenchable depuis un navigateur.

**Une réinitialisation révoque toutes les sessions**, sinon une session détournée survit au changement de mot de passe.

**Un envoi raté ne fait jamais échouer une inscription.** Le compte est créé, l'échec journalisé. L'inverse ferme la boutique à chaque panne de Brevo.

**Le serveur fait autorité sur la session, et l'interface s'y range.** Le cookie est en HttpOnly : le JavaScript ne peut pas savoir qu'il a expiré, qu'il a été révoqué depuis un autre appareil, ou que le serveur a été redéployé. Le seul signal est un 401 sur un appel qui n'aurait pas dû en produire — `src/api/client.ts` le remonte alors au contexte, qui vide l'état et laisse les pages protégées renvoyer vers la connexion. Sans cela, l'application affiche un client connecté et l'erreur surgit au pire endroit : au moment de confirmer une commande.

**`--fresh` est refusé sur la base de travail** sans `--force`. Il supprime toutes les tables, comptes et commandes compris — y compris ceux de quelqu'un dont l'onglet est resté ouvert. Sur la base de test, c'est le comportement attendu et le garde ne s'applique pas.

## L'accueil n'est pas un décor

Les quatre paires du carrousel **sont** des produits du catalogue, désignés par leur slug. Prix, coloris, texte et stock viennent de la même source que la boutique : acheter depuis l'accueil ajoute exactement l'article affiché, et le nom du modèle mène à sa fiche.

Les prix étaient en dollars et les boutons inertes. Ils sont en F CFA, et « Ajouter au panier » ouvre un panneau de tailles alimenté par le stock réel — les tailles épuisées sont barrées. « Acheter » fait la même chose puis mène au paiement.

**Les pastilles de coloris ne mentent plus.** Il y en avait trois par paire alors que chaque modèle n'existe qu'en un seul coloris au catalogue : choisir « Argent métallisé » sur la Shox TL aurait ajouté la noire. La rangée affiche désormais les coloris réels du modèle. Avec un seul, elle annonce lequel ; dès qu'un second entrera au catalogue, elle redeviendra un vrai sélecteur et changera le produit ajouté.

Le défilement automatique **se suspend** pendant le choix d'une taille : voir le slide changer sous son doigt au moment d'acheter est la meilleure façon d'ajouter au panier autre chose que ce qu'on visait.

La liste des quatre slugs et les fonds peints à la main vivent dans `Hero.tsx`. Ils passeront en base à la partie administration — c'est pourquoi la liste ne contient que des slugs et rien de recopié.

## Administration

`/admin`, dans le thème du site : fond sombre, Archivo Black, angles vifs. Huit écrans — vue d'ensemble, commandes, sneakers, maillots, clients, messagerie, vitrine, réglages.

### L'accès

Un drapeau `is_admin` sur `customers`, pas un second système d'authentification : sessions, limitation de débit, bcrypt et révocation sont déjà écrits et testés ; les dupliquer doublerait la surface à sécuriser pour une équipe de deux.

Le premier administrateur naît d'une commande sur le serveur, hors du web par construction :

```bash
php bin/admin.php lister
php bin/admin.php promouvoir lemaye@exemple.com
php bin/admin.php retrograder lemaye@exemple.com
```

Le compte doit exister : on promeut un client, on ne fabrique pas un administrateur — c'est ce qui garantit un mot de passe choisi par lui et jamais transmis. La commande refuse de retirer le dernier accès. Ensuite, un administrateur peut en promouvoir d'autres depuis l'interface.

**La garde est appliquée en un seul point**, dans `App::addAdminRoutes`, et non recopiée au début de chaque méthode : une garde répétée trente fois finit par être oubliée une fois, et cet oubli-là ouvre la boutique. Un test parcourt les vingt-huit routes aux trois niveaux d'accès — visiteur, client, administrateur.

Le drapeau voyage jusqu'au front pour orienter la navigation, mais ne protège rien : le serveur revérifie à chaque appel.

### Les visuels

`products.image` et `jerseys.image` désignent un fichier de `backend/public/uploads`, servi par le serveur et absent du dépôt. Les quatre premiers rendus, eux, ont été intégrés au front avant qu'il existe une administration : ce sont des modules compilés par Vite, et la base n'en gardait que le nom de fichier — un nom qui ne pointait sur rien côté serveur. L'administration affichait donc ces quatre paires sans leur image.

`php bin/visuels.php` fait le pont : il passe les rendus du dépôt par `ImageStore`, exactement comme un envoi depuis l'administration — même ré-encodage en WebP, même limite de 1200 px. Les quatre sont passés de 6,3 Mo à 467 Ko, soit 93 % en moins. Le script est idempotent et ne touche jamais à un visuel déjà en place ; `--liste` n'écrit rien et dit seulement l'état.

Le semoir, lui, **ne remplace plus un visuel déjà enregistré**. Il portait le même défaut que s'il avait écrasé le stock : rejoué après un envoi depuis l'administration, il aurait effacé la référence et laissé le fichier orphelin sur le disque.

Enfin, une référence qui ne se charge pas est **dite**, et non masquée. Masquer confondait deux situations opposées — un article sans visuel, qui attend une photo, et un visuel manquant sur le serveur, qui attend une réparation.

**Un administrateur ne passe pas par l'espace client.** Sa connexion le dépose sur le tableau de bord, l'icône de compte de la boutique y mène aussi, et `/espace-client` l'y renvoie. Ses commandes et ses favoris ne concernent pas son travail, et le déposer dans une page client avec un bouton « Administration » laisse croire que ce bouton s'affiche pour tout le monde. Une destination explicite garde la priorité : celui qu'on avait interrompu au paiement revient au paiement. Conséquence assumée : ses propres coordonnées et son mot de passe vivent désormais dans **Réglages → Mon compte**, sans quoi il n'aurait plus aucun moyen de les changer.

### Ce que l'administration peut faire

**Commandes** — liste filtrable, recherche par référence, nom ou téléphone, détail complet, et surtout le statut qui avance : en préparation → confirmée → expédiée → livrée, ou annulée. Sans retour en arrière. Une annulation **rend le stock**. La livraison constate le règlement. Le client reçoit un e-mail à chaque étape.

**Catalogue** — créer, modifier, retirer de la vente, corriger le stock taille par taille. L'adresse de la page se déduit du nom : le gérant n'a pas à savoir ce qu'est un slug.

**Visuels** — téléversement depuis le navigateur. Toute image est **ré-encodée** en WebP à 1200 px, jamais recopiée telle quelle. Trois bénéfices d'un geste : un PHP dissimulé dans les octets d'un PNG ne survit pas au décodage ; le poids s'effondre — mesuré, 1 804 Ko → 127 Ko, soit 93 % de moins, transparence conservée ; et tous les visuels finissent au même format. Le dossier refuse par ailleurs d'exécuter quoi que ce soit.

**Clients** — consulter, suspendre, anonymiser. Suspendre ferme les sessions ouvertes sur-le-champ. L'anonymisation est la réponse au droit à l'effacement que promettent déjà les pages légales : nom, adresse, téléphone et favoris disparaissent, **y compris dans les coordonnées recopiées des commandes passées**, mais la commande reste — c'est une pièce comptable, et la clé étrangère l'interdit d'ailleurs.

**Messagerie** — les messages de contact avec leur suivi, les inscrits à la lettre d'information avec export et resynchronisation rejouable vers Brevo.

**Vitrine** — les quatre paires du carrousel d'accueil, dans l'ordre. Un article retiré de la vente ne peut pas y figurer : l'accueil afficherait un vide là où tout le monde regarde en premier.

**Réglages** — coordonnées affichées sur le site, adresse qui reçoit les commandes, tarifs de livraison. Le montant appliqué à une commande est celui enregistré ici, jamais celui envoyé par le navigateur.

**Journal** — une ligne par action. À deux personnes aux commandes, « qui a changé ce prix ? » finit toujours par se poser.

### L'encoche de la barre latérale

L'élément actif n'est pas surligné, il est **découpé dans la barre** : une pastille claire à ras du bord droit, et deux angles concaves qui la pincent. Ces angles n'existent pas en CSS ; on les fabrique avec deux pseudo-éléments transparents dont une `box-shadow` étalée peint la couleur de la barre tout autour d'un coin arrondi — c'est ce vide qui donne le creux.

La référence faisait déborder la pastille sur une zone de contenu **blanche**, où l'épaulement clair se fondait. Sur fond sombre, ce même débordement ressort comme deux languettes. La pastille s'arrête donc au bord, et ce sont les creux sombres qui la pincent : même silhouette, transposée.

Tout le reste garde les angles vifs du site. L'encoche est ainsi un accent rare, et non une pièce rapportée d'une autre direction artistique.

## Commandes

**Le serveur ne fait confiance à rien de ce que le navigateur envoie sur l'argent.** Le panier transmet quels articles, quelle taille, quelle quantité. Les prix, les frais de livraison et la disponibilité sont relus en base. Sans cela, n'importe qui commande à 0 F en modifiant une requête — et le stock affiché dans le navigateur ne prouve rien.

C'est cette contrainte, et non le confort, qui a imposé de porter le catalogue en base.

**Une commande fige ses données**, contrairement au panier et aux favoris qui ne stockent que des identifiants pour suivre le catalogue. Le prix, le titre et la taille sont recopiés dans `order_items` au moment de l'achat : changer un tarif ne doit pas réécrire l'histoire, ni faire mentir une facture déjà émise. Les coordonnées de livraison sont recopiées pour la même raison — et parce qu'une commande peut être adressée à quelqu'un d'autre.

**Le stock est décrémenté dans la transaction**, avec la condition dans la requête :

```sql
UPDATE product_variants SET stock = stock - ? WHERE product_id = ? AND size = ? AND stock >= ?
```

Si zéro ligne n'est touchée, c'est qu'une autre commande est passée entre la lecture et l'écriture : la transaction tombe entièrement. Vérifier puis écrire en deux temps laisserait deux clients acheter la même dernière paire.

**Le paiement en ligne est refusé côté serveur** tant qu'aucun agrégateur n'est branché, et l'option est montrée désactivée dans le tunnel. L'interface est contournable : le refus doit exister là où il compte.

**La référence est générée par le serveur** — `RCC-AAMMJJ-XXXX`. Elle l'était dans le navigateur, où deux clients simultanés pouvaient repartir avec la même. Le suffixe est aléatoire pour qu'un client ne puisse pas déduire le numéro d'un autre, et donc le volume d'affaires de la boutique.

### Le catalogue en base

`php migrations/seed.php` charge `migrations/catalogue.json`, exporté depuis les modules TypeScript qui restent la source d'écriture tant qu'il n'y a pas d'interface d'administration.

Le semoir est **idempotent et ne touche jamais au stock d'une variante existante** : le rejouer après quelques ventes ressusciterait des paires déjà vendues. Même principe pour les tarifs de livraison.

Les composants du front lisent encore leurs modules locaux pour l'affichage ; `GET /api/products` et `GET /api/jerseys` renvoient exactement la même forme, la bascule sera mécanique. En attendant, le stock affiché peut être en retard sur le stock réel — c'est cosmétique, le serveur reste seul juge à la commande.

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
└── tests/                  262 tests

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
3. ~~**Commandes**~~ — faites. Catalogue en base, stock réel, lignes figées.
4. **Agrégateur de paiement** — à choisir (KkiaPay, FedaPay, CinetPay sont les candidats béninois à comparer). Le reste du tunnel l'attend.
5. ~~**Administration**~~ — faite. Huit écrans, 28 routes, journal des actions.
6. **Front sur l'API du catalogue** — remplacer les imports de `src/data/` par des `fetch`, pour que le stock affiché cesse d'être en retard.
7. **Facture PDF** — le bouton existe, inerte. Le format reste à définir.
8. **Contact et lettre d'information** — `POST /api/contact`, `POST /api/newsletter`.

### Ce qui n'est pas fonctionnel

Chaque point ci-dessous porte un `TODO` à l'endroit exact dans le code.

| Manque | Détail |
|---|---|
| Commandes | Le tunnel va jusqu'au bout et affiche une référence, mais **rien n'est enregistré** et la référence est générée côté navigateur. À câbler avant toute mise en ligne. |
| Paiement en ligne | Refusé par le serveur, désactivé dans le tunnel. Aucun agrégateur n'est branché. Seul le paiement à la livraison fonctionne — et il fonctionne entièrement. |
| Téléchargement de facture | Bouton présent et désactivé. Le format n'est pas arrêté. |
| Stock affiché | Le front lit encore ses modules locaux : après une vente, le nombre affiché peut être en retard. Le serveur, lui, refuse une commande au-delà du stock réel. |
| Domaine vérifié dans Brevo | Aucun domaine n'est authentifié : Brevo ne peut pas signer pour `gmail.com`, et réécrit donc le Return-Path en `@…brevosend.com`. Ce compte a pourtant un historique d'ouvertures sur de nombreuses adresses Gmail, donc **ce n'est pas bloquant aujourd'hui**. Cela reste à faire avant la mise en ligne : la délivrabilité d'un domaine authentifié ne dépend pas de la réputation partagée d'un sous-domaine d'ESP. |
| Clé d'API Brevo | Transmise en clair pendant le développement : à régénérer avant la mise en ligne. |
| Visuels produits | 16 sneakers sur 20 et les 12 maillots n'ont pas de rendu. Les cartes basculent sur un halo dans la couleur du coloris avec « visuel à venir ». Déposer le PNG et remplacer `image: null` par l'import suffit. |
| Informations légales | Tout ce qui est entre crochets dans `data/legal.ts` : RCCM, IFU, hébergeur, numéro APDP. Ce sont des identifiants officiels, ils n'ont pas été inventés. |
| Tarifs de livraison | 1 000 / 1 500 / 2 500 F CFA sont des valeurs de remplacement, en haut de `Checkout.tsx`. |
| Coordonnées | Téléphone, e-mail et liens réseaux sont des valeurs de remplacement, en haut de `Footer.tsx` et `Contact.tsx`. |
