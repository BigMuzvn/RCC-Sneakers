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

## 5. Pièges Tailwind rencontrés

Deux bugs invisibles à la lecture du code, tous deux trouvés en inspectant le rendu réel.

**Une opacité hors échelle est silencieusement supprimée.** Le panneau de choix de taille sur les cartes produit utilisait `bg-[#0B0C0E]/94`. Or **94 n'existe pas** dans l'échelle d'opacité de Tailwind (…, 90, 95, 100) : la classe n'est jamais générée, aucune erreur n'est levée, et le panneau se retrouve sans aucun fond. Les tailles flottaient directement sur la photo du produit, illisibles. Vérifiable en compilant le CSS et en cherchant la classe — elle est absente.

**Une marge négative sur un élément positionné par `bottom` descend l'élément.** En corrigeant l'écart RCC/titre, la première tentative a aggravé le décalage parce que j'avais supposé l'inverse. La mesure l'a montré immédiatement.

## 6. Le panier

Trois décisions de conception qui ne se devinent pas à la lecture :

**Le panier ne persiste que des identifiants.** Stocker les lignes complètes paraît plus simple, mais les URL d'images sont des empreintes de build : elles changent à chaque compilation, et un panier enregistré avant un redéploiement afficherait des images mortes. Seuls `{ type, id, size, qty }` sont écrits, les lignes sont reconstruites depuis le catalogue au chargement.

**Ajouter n'ouvre pas le tiroir.** C'était le comportement initial, corrigé à l'usage : pour ajouter trois paires, il fallait fermer le panneau trois fois. Une notification transitoire le remplace.

**Les cartes utilisent un lien étiré, pas un lien englobant.** Un `<button>` dans un `<a>` est du HTML invalide. La carte est donc un `<article>` avec un lien en `absolute inset-0`, et le bouton d'ajout rapide passe au-dessus avec un `z-index` supérieur.

## 7. Méthode de travail

Chaque changement d'interface est **vérifié dans un vrai navigateur** avant d'être annoncé : Playwright, captures en 1400 / 1280 / 768 / 390 / 375 / 360 px, et contrôle programmatique de l'absence de débordement horizontal et vertical.

Ce sont ces passages qui ont révélé la paire suspendue totalement invisible, le swoosh noir sur fond noir, la boîte à chaussures collée aux icônes mobiles, l'encart de statistiques débordant de son cadre, le panneau de tailles sans fond, et les décalages de titre ci-dessus. Aucun de ces défauts n'était visible dans le code.

Les mesures sont faites **numériquement** plutôt qu'à l'œil : position des titres au pixel, largeur de défilement comparée à la largeur visible, totaux du panier recalculés après chaque action, styles calculés lus dans le navigateur. C'est ce qui a permis d'affirmer que les titres des cinq pages internes sont alignés au pixel près, et pas seulement qu'ils en ont l'air.

## 8. Le backend

Cinq décisions et deux pièges qui ne se devinent pas à la lecture du code.

**Le hachage est bcrypt, pas Argon2id.** Argon2id est meilleur et disponible sur la machine de développement. Mais beaucoup de mutualisés compilent PHP sans sodium : un hachage écrit ici et illisible là-bas ferait échouer `password_verify` pour **tous les comptes**, sans message exploitable. La portabilité l'emporte sur la force de l'algorithme quand l'alternative est d'enfermer la clientèle dehors.

**Les colonnes indexées sont en `VARCHAR(191)`.** En utf8mb4 chaque caractère peut occuper 4 octets : un index sur 255 caractères pèse 1020 octets et dépasse la limite de 767 de certaines configurations InnoDB anciennes. 191 × 4 = 764, juste en dessous. C'est la raison — souvent recopiée sans être comprise — du 191 qu'on croise partout.

**Pas de sessions natives PHP.** Sur un mutualisé, leurs fichiers atterrissent souvent dans un répertoire temporaire partagé entre comptes, et `session.save_path` n'est pas toujours modifiable. Un jeton en base couvre la session ordinaire *et* le « se souvenir de moi » — seule la durée diffère — et se révoque réellement côté serveur. Ni une session PHP ni un JWT ne se reprennent une fois émis.

**L'autoloader est écrit à la main.** Huit lignes de PSR-4. S'appuyer sur celui de Composer aurait annulé la promesse de « zéro dépendance en production », puisqu'il vit dans `vendor/`.

**Le téléphone est stocké deux fois.** Forme canonique pour l'unicité, forme saisie pour l'affichage. Sans cette séparation, `+229 01 97 00 00 00` et `0197000000` ouvrent deux comptes pour la même personne, dont les commandes se répartissent ensuite entre deux dossiers.

### Deux pièges rencontrés

**`TRUNCATE` est 426 fois plus lent que `DELETE`.** Mesuré sur les quatre tables du schéma, vides : 4 260 ms contre 10 ms. Sur InnoDB, `TRUNCATE` supprime et recrée le fichier de tablespace, donc il touche le disque là où `DELETE` ne fait rien. La suite de tests, qui repart d'une base vide à chaque test, est passée de 31 s à 1,6 s. Ce n'est pas un détail de confort : une suite lente cesse d'être lancée.

**Un cookie posé n'est pas relisible dans la même requête.** Le pot de cookies séparait l'entrant du sortant, ce qui paraît propre. Résultat : le contrôleur d'inscription ouvrait la session puis demandait « qui est connecté ? » et obtenait `null`, parce qu'il interrogeait les cookies reçus et non ceux qu'il venait d'écrire. La réponse renvoyait un client vide. Quatre tests l'ont attrapé d'un coup ; aucune relecture du code ne l'aurait montré.

### Ce que les tests protègent en priorité

Les assertions qui comptent le plus ne portent pas sur le chemin heureux.

Un compte inconnu et un mot de passe faux doivent donner la **même réponse au caractère près** — le test compare les deux charges utiles entre elles plutôt qu'à une valeur attendue. Sinon le formulaire de connexion devient un annuaire permettant de vérifier qui est client.

Le même numéro écrit autrement doit être reconnu comme déjà pris : c'est ce test qui justifie toute la normalisation.

Et une panne du service d'envoi ne doit pas empêcher une inscription d'aboutir. Le test simule un Brevo indisponible et exige quand même un 201 : sinon une panne chez un tiers fermerait la boutique aux nouveaux clients.

## 9. L'espace client

### Où placer le mur

Un site marchand doit décider ce qu'il refuse à un visiteur sans compte. La réponse retenue tient en une phrase : **rien, sauf payer**.

Parcourir, remplir un panier, changer d'avis — tout reste libre. C'est au moment de valider que le compte devient nécessaire, parce que c'est là qu'il sert réellement à quelque chose : une adresse de livraison, un suivi, une facture.

Le compte non vérifié n'est pas un second mur. Beaucoup de clients s'inscriront avec une adresse secondaire, et exiger un clic dans un e-mail peut-être classé en indésirables refoulerait de vrais acheteurs. Le bandeau de rappel énonce donc le **risque concret** — sans adresse confirmée, aucun lien de réinitialisation ne peut parvenir — plutôt qu'une injonction vague. C'est ce qui décide quelqu'un à cliquer.

### Confirmer plutôt que rediriger

Au clic sur « Procéder au paiement » sans compte, le tiroir ouvre une fenêtre de confirmation au lieu d'emmener directement vers l'inscription.

Une redirection sèche à cet instant précis se lit comme un mur : le client ne comprend pas ce qui vient de se passer, et sa crainte immédiate est d'avoir perdu son panier. La fenêtre y répond avant même que la question soit formulée — « votre panier de N articles est conservé » — puis propose inscription ou connexion.

`/checkout` porte malgré tout la même garde, car l'adresse reste atteignable par un signet ou après expiration de la session. Une protection qui n'existe qu'à un seul point d'entrée n'en est pas une.

### Le halo change d'axe

`SideShoe` est la variante latérale de la paire suspendue. Le visuel entre par le bord droit, donc le halo suit cet axe : dégradé radial ancré à droite, et non en haut. Garder l'ancrage supérieur ferait venir la lumière d'un endroit où il n'y a rien.

Les tons sont relevés par famille de teinte plutôt qu'en fréquence brute. Le comptage simple donnait le crème à 45 %, qui remplit la chaussure sans la caractériser ; le regroupement par teinte fait ressortir le **périwinkle à 30 %** et le **bordeaux-rose à 23 %**, qui sont ce qu'on retient de la paire. Ce sont eux qui éclairent la page.

### Une gouttière, pas un empilement

Un visuel qui entre par la droite impose de réserver cette bande. Sans gouttière, la première version couvrait le bouton « Renvoyer le lien » et descendait jusque sur la carte des commandes.

Sur téléphone, cette gouttière prendrait la moitié de l'écran. Mesuré à 390 px : la paire passait en travers du bandeau de vérification, qui devenait illisible. Elle y est donc masquée et **seul le halo demeure** — c'est lui le dispositif, pas la photographie.

### Ce que les tests de parcours ont attrapé

Un faux négatif instructif : `waitForURL` de Playwright rend la main dès le changement d'historique, **avant** que React ait rendu la nouvelle route. Le test voyait la bonne URL et capturait l'ancienne page, concluant à l'absence du bandeau de vérification. Attendre le titre plutôt que l'URL corrige la mesure.

Le parcours qui compte le plus a été vérifié de bout en bout, jeton extrait du journal d'envoi : bandeau affiché → lien réel ouvert → adresse confirmée → bandeau disparu → même lien rejoué et refusé.

## 10. Les commandes

### Une règle qui a décidé de l'architecture

Le serveur ne peut faire confiance à rien de ce que le navigateur dit sur l'argent. Un panier envoie *quels* articles, *quelle* taille, *quelle* quantité — jamais leur prix. Sinon il suffit de modifier une requête pour commander à 0 F.

C'est cette phrase, et elle seule, qui a imposé de porter le catalogue en base. Ce n'était pas une question de confort ni de propreté : sans catalogue côté serveur, il n'existe aucune source de vérité à laquelle comparer ce que le client annonce.

Le stock suit le même raisonnement. Tant qu'il ne vit que dans les modules du front, il est décoratif — rien n'empêche deux clients d'acheter la même dernière paire.

### Figer, à l'inverse du panier

Le panier et les favoris ne stockent que des identifiants, pour suivre le catalogue : si un prix baisse, le panier doit baisser avec lui.

Une commande fait exactement l'inverse. Le prix, le titre, le sous-titre et la taille sont **recopiés** dans `order_items` au moment de l'achat. Changer un tarif ne doit pas réécrire une vente passée, et une facture émise l'an dernier ne doit pas afficher le prix d'aujourd'hui. Les coordonnées de livraison sont recopiées aussi — parce qu'une commande peut être adressée à quelqu'un d'autre, et parce qu'elle doit rester lisible si le client change ensuite de numéro.

C'est le même projet, deux règles opposées, chacune juste dans son contexte.

### La condition est dans la requête, pas avant

```sql
UPDATE product_variants SET stock = stock - ? WHERE product_id = ? AND size = ? AND stock >= ?
```

Lire le stock, le comparer, puis écrire, laisse une fenêtre entre la lecture et l'écriture. Sous la charge d'un lancement, cette fenêtre suffit à vendre deux fois la même paire. En plaçant la condition dans l'`UPDATE`, c'est la base qui arbitre : si zéro ligne n'est touchée, la course est perdue et la transaction tombe en entier.

La clé étrangère `orders.customer_id` est en `ON DELETE RESTRICT` et non `CASCADE`. Une commande est une pièce comptable : supprimer un compte ne doit pas effacer l'historique des ventes.

### Refuser là où ça compte

Aucun agrégateur de paiement n'est branché. L'option « mobile money / carte » reste visible dans le tunnel, désactivée et étiquetée « bientôt » — mais elle est aussi **refusée par le serveur**. L'interface est contournable ; une garde qui n'existe que dans le navigateur n'en est pas une.

Accepter une commande « en ligne » sans moyen de l'encaisser reviendrait à promettre un règlement impossible.

### La référence a changé de camp

Elle était fabriquée dans le navigateur — `RCC-${Date.now().slice(-6)}`. Deux clients simultanés pouvaient repartir avec la même, et une référence prévisible laisse deviner le numéro des autres, donc le volume d'affaires de la boutique.

Elle est maintenant générée par le serveur, au format `RCC-AAMMJJ-XXXX` : la date situe la commande, le suffixe aléatoire la rend non devinable, et l'unicité est garantie par une contrainte en base.

### Un routeur qui apprend les paramètres

`GET /orders/{reference}` a forcé le routeur à dépasser la correspondance exacte. Deux règles en sont sorties, toutes deux testées : une route fixe l'emporte sur une route à paramètre — sinon `/orders/recents` serait lu comme une référence de commande — et un paramètre ne franchit jamais une barre oblique, car il désigne un segment et non un chemin.

### Ce qu'une fausse piste a coûté

En vérifiant une mesure, un contrôle a été lancé quatre secondes après un envoi d'e-mail. L'index de Brevo accuse quelques dizaines de secondes de retard : la conclusion « l'envoi a échoué » était fausse. La leçon vaut au-delà de Brevo — **un service distant qui répond « pas encore » ne dit pas « jamais »**, et une vérification trop rapprochée fabrique des pannes imaginaires.
