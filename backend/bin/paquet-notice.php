<?php

/**
 * Le mode d'emploi glissé dans l'archive. Séparé du fabricant pour que le
 * texte reste lisible, et modifiable sans toucher au code.
 */

return <<<TEXTE
RCC SNEAKERS — MISE EN LIGNE
============================

Cette archive contient tout ce qui doit partir chez l'hébergeur.


1. LA BASE DE DONNÉES
---------------------

Créez une base MySQL depuis le panneau de l'hébergeur, puis ouvrez
phpMyAdmin et importez « base-a-importer.sql ».

Il emporte le schéma, les 20 sneakers et 12 maillots avec toutes leurs
tailles, vos réglages et coordonnées, vos commandes, et votre compte
administrateur déjà promu. Vous vous connecterez avec votre mot de passe
habituel.


2. LES QUATRE VALEURS À RENSEIGNER
----------------------------------

Ouvrez « htdocs/config.php » et remplacez ce qui est marqué << À REMPLIR >> :

  - app.url  : l'adresse publique du site, par exemple
               https://rccsneakers.infinityfreeapp.com  (sans barre finale)

               Attention à celle-là : c'est elle qui fabrique les liens des
               e-mails. Laissée fausse, aucune vérification d'adresse ni
               réinitialisation de mot de passe n'aboutira, et rien ne le
               signalera.

  - db.host / db.name / db.user / db.pass : les identifiants MySQL donnés
               par l'hébergeur au moment de la création de la base.

Rien d'autre n'est à toucher.


3. LE TÉLÉVERSEMENT
-------------------

Envoyez par FTP **le contenu** du dossier « htdocs » de cette archive dans
le dossier « htdocs » de l'hébergeur. Pas le dossier lui-même : son contenu.

Vous devez y retrouver, côte à côte :

    index.html          le site
    assets/             ses fichiers
    autoload.php        le code de l'API
    src/                    "
    config.php              "
    api/                le point d'entrée et les visuels

Vérifiez que les fichiers commençant par un point sont bien partis —
« .htaccess » et « .user.ini ». Beaucoup de clients FTP les cachent par
défaut, et sans eux le site renvoie une erreur sur chaque page autre que
l'accueil.


4. VÉRIFIER
-----------

Ouvrez le site. Dans l'ordre :

  - l'accueil s'affiche avec les quatre paires en vitrine ;
  - /boutique montre 20 paires avec leurs visuels ;
  - tapez directement une adresse comme /boutique/nike-air-max-95-neon :
    si elle renvoie une erreur du serveur, le « .htaccess » n'est pas parti ;
  - connectez-vous avec votre compte : vous devez atterrir sur le tableau
    de bord, pas sur l'espace client ;
  - depuis l'administration, changez le téléphone dans Réglages et
    rechargez la boutique : le pied de page doit suivre.

Pour l'envoi des e-mails, demandez une réinitialisation de mot de passe sur
votre propre adresse. Si le message arrive, toute la chaîne fonctionne.


CE QUI N'EST PAS DANS L'ARCHIVE
-------------------------------

Le paiement en ligne : aucun agrégateur n'est branché. Seul le paiement à
la livraison est proposé, et il fonctionne entièrement.

Les mentions légales attendent encore le RCCM, l'IFU, le numéro APDP et le
nom de l'hébergeur. Ce sont des identifiants officiels : ils n'ont pas été
inventés, et apparaissent entre crochets.

TEXTE;
