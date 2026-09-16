# Authentification — conception

*16 septembre 2026. Première tranche du backend. Débloque l'espace client.*

## Contexte

Le front-end est en avance sur le back : `backend/` est vide, et les formulaires de
[`Compte.tsx`](../../../frontend/src/components/Compte.tsx) écrivent déjà le contrat d'API.
Inscription : `name`, `email`, `phone`, `password`, `terms`.
Connexion : `identifier` — **e-mail ou téléphone** — `password`, `remember`.

Le téléphone est un identifiant de connexion de plein droit. À Cotonou, beaucoup de clients
ont un numéro et pas d'adresse mail consultée.

## Contraintes retenues

| Décision | Raison |
|---|---|
| Hébergement mutualisé cPanel | Cible réelle : PHP 8 + MySQL, Apache, pas de shell |
| **Zéro dépendance Composer en production** | Déploiement par FTP, tourne sur un forfait à quelques milliers de F CFA |
| Jeton en base + cookie HttpOnly, `SameSite=Lax` | Jeton illisible en JavaScript ; `Lax` neutralise le CSRF sans jeton dédié |
| `PASSWORD_BCRYPT`, coût 12 | Argon2id existe en local mais manque sur beaucoup de mutualisés |
| SQL conservateur | Dev en MySQL 8.4, production probable en 5.7 / MariaDB 10.x |
| Brevo pour l'e-mail transactionnel | Compte existant, formule gratuite, 300 envois/jour |

### Portabilité SQL — ce qui est proscrit

Pas de CTE, pas de fonction de fenêtrage, pas de contrainte `CHECK` (silencieusement ignorée
en 5.7), pas de collation `utf8mb4_0900_ai_ci` (8.0+ uniquement). Collation `utf8mb4_unicode_ci`.

Colonnes indexées en `VARCHAR(191)` : en utf8mb4 un index sur 255 caractères pèse 1020 octets
et dépasse la limite de 767 octets de certaines configurations InnoDB anciennes.

### Le piège du hachage

Hacher en Argon2id sur une machine qui le possède, puis déployer sur un hôte compilé sans
sodium, donne un `password_verify` qui échoue pour **tous les comptes**, sans message
exploitable. `PASSWORD_BCRYPT` est explicite et disponible partout depuis PHP 5.5.

## Schéma

`customers` — `name`, `email`, `phone`, `phone_display`, `password_hash`, `email_verified_at`.
Unicité sur l'e-mail **et** sur le téléphone.

Le téléphone est stocké deux fois : forme canonique en chiffres seuls pour l'unicité et la
recherche, forme saisie pour l'affichage. Sans cela `+229 01 97 00 00 00` et `0197000000`
créeraient deux comptes pour la même personne.

`auth_tokens` — « se souvenir de moi », schéma sélecteur/validateur. La base ne stocke que le
SHA-256 de la moitié secrète : une fuite ne donne aucune session utilisable, et chaque appareil
reste révocable individuellement.

`customer_tokens` — vérification d'e-mail et réinitialisation de mot de passe. Même mécanique,
donc une seule table avec une colonne `purpose`. SHA-256 également, usage unique.
1 h pour un mot de passe, 24 h pour une vérification.

`auth_attempts` — tentatives par identifiant et par IP. L'identifiant y est haché : ce journal ne doit pas devenir une liste exploitable des adresses de la clientèle. Une boutique qui encaisse des paiements
sans limitation de débit sur sa connexion se fait vider ses comptes en une nuit.

## Routes

| Route | Rôle |
|---|---|
| `POST /api/auth/register` | crée le compte, connecte, envoie le mail de vérification |
| `POST /api/auth/login` | `identifier` e-mail ou téléphone |
| `POST /api/auth/logout` | détruit la session, révoque le jeton rémanent |
| `GET  /api/auth/me` | client courant ou 401 — la route dont dépend l'espace client |
| `POST /api/auth/verify-email` | consomme le jeton |
| `POST /api/auth/resend-verification` | |
| `POST /api/auth/forgot-password` | |
| `POST /api/auth/reset-password` | |

Enveloppe constante : `{ "data": … }` ou `{ "error": { code, message, fields } }`.
Messages en français, champs en `snake_case` comme `src/data/`.

## Règles de sécurité non négociables

**Pas d'oracle d'énumération.** Une adresse inconnue et un mot de passe faux renvoient le même
message et le même délai. `forgot-password` répond toujours identiquement. Sinon les formulaires
deviennent un annuaire permettant de vérifier qui est client.

**Limitation de débit sur les routes d'envoi.** Le forfait Brevo est à 300 mails par jour. Sans
limite, marteler `forgot-password` consomme le quota en une minute et coupe *tous* les mails,
confirmations de commande comprises, jusqu'au lendemain — une panne totale déclenchable par un
inconnu depuis un navigateur. Limitation par adresse et par IP.

**Une réinitialisation révoque toutes les sessions.** Sinon une session détournée survit au
changement de mot de passe, ce qui vide le geste de son sens.

**Un envoi raté ne fait jamais échouer une inscription.** Le compte est créé, l'échec journalisé,
le client peut redemander. L'inverse ferme la boutique à chaque panne de Brevo.

**La connexion n'est pas bloquée par la vérification d'e-mail.** Beaucoup de clients s'inscriront
avec une adresse secondaire ; exiger un clic dans un mail peut-être classé en spam refoule de
vrais acheteurs. Bandeau de rappel dans l'espace client, vérification exigée seulement pour
réinitialiser par e-mail.

## Structure

```
backend/
├── public/index.php        contrôleur frontal — seul fichier exposé
├── src/
│   ├── Database.php  Router.php  Request.php  Response.php
│   ├── Validator.php  Phone.php
│   ├── Session.php         cookie HttpOnly + « se souvenir de moi »
│   ├── RateLimiter.php
│   ├── Mailer/             Mailer.php (interface), BrevoMailer, ArrayMailer (tests)
│   └── Controllers/AuthController.php
├── migrations/001_auth.sql + run.php
├── config.example.php      config.php réel git-ignoré
└── tests/
```

`src/` reste **hors racine web** en production : `public_html/api/index.php` d'un côté, le code
de l'autre.

Le mailer passe derrière une interface : les tests capturent les envois au lieu de les émettre,
donc ils ne brûlent pas le quota.

## Développement

Vite proxie `/api` vers `php -S localhost:8000`. Le navigateur ne voit qu'une origine, donc les
cookies se comportent en dev comme en production.

PHPUnit en dépendance **de développement seulement** — `vendor/` ne part pas sur l'hébergeur.
Tests sur une base séparée `rcc_sneakers_test`, jamais sur les données de travail.

## Prérequis de mise en ligne

**Un domaine vérifié dans Brevo.** Le seul expéditeur vérifié est une adresse `@gmail.com`.
`gmail.com` publie `p=none`, donc rien n'est rejeté — mais son SPF est `redirect=_spf.google.com`,
qui n'inclut pas les serveurs Brevo. Tout envoi échoue SPF et l'alignement DKIM, ce que Gmail et
Outlook pèsent lourdement en score anti-spam. Un mail de réinitialisation en indésirables est une
fonctionnalité morte. Il faut un domaine propre avec ses enregistrements DKIM/SPF.

**Renommer l'expéditeur**, aujourd'hui « ProductHunt Lite ».

**Régénérer la clé d'API**, transmise en clair dans une session de développement.

## Hors périmètre

Espace client (tranche suivante, dépend de `GET /api/auth/me`), commandes, catalogue servi par
l'API. Authentification à deux facteurs, connexion par réseau social : non prévues.

---

## Écarts constatés à l'implémentation

Trois décisions ont changé en cours d'écriture. Elles sont notées ici parce que
le raisonnement compte davantage que la conclusion.

**Les sessions natives de PHP ont été abandonnées.** Sur un mutualisé, les
fichiers de session atterrissent souvent dans un répertoire temporaire partagé
entre comptes, et `session.save_path` n'est pas toujours modifiable. Un jeton en
base couvre à la fois la session ordinaire et le « se souvenir de moi » — seule
la durée diffère — évite ce terrain, survit à un changement de serveur, et se
révoque réellement côté serveur. Ni une session PHP ni un JWT ne se reprennent
une fois émis.

**La colonne `succeeded` de `auth_attempts` a été retirée.** Seules les
tentatives qui consomment le quota sont écrites, et une connexion réussie efface
les lignes de l'identifiant. La colonne codait une distinction dont le compteur
n'a pas l'usage.

**L'autoloader de Composer n'est pas utilisé en production.** S'appuyer dessus
aurait annulé la promesse de « zéro dépendance » : `vendor/` ne contient que
PHPUnit et ne part pas sur l'hébergeur. Huit lignes de PSR-4 dans
`backend/autoload.php` suffisent, et l'API n'a plus besoin que de `src/`.

## Vérifications effectuées

109 tests, 190 assertions, tous au vert. Au-delà des tests, le parcours complet a
été exercé sur un vrai serveur HTTP — inscription, session, déconnexion,
connexion par téléphone sous plusieurs écritures — puis à travers le proxy Vite,
pour vérifier ce que les tests ne couvrent pas : décodage JSON, cookies réels,
`Request::fromGlobals()`.

L'envoi Brevo a été confirmé côté prestataire : requête à 15:51:59, **livraison à
15:52:00**.

Deux mesures faites en passant. `TRUNCATE` sur InnoDB coûte 4 260 ms là où
`DELETE` coûte 10 ms sur les mêmes tables vides — il supprime et recrée le
fichier de tablespace. La suite est passée de 31 s à 1,6 s avant l'ajout des
tests d'API. Et le coût bcrypt est configurable pour que les tests hachent à 4
plutôt qu'à 12.
