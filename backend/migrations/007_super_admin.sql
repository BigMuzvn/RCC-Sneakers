-- Deux rangs d'administration.
--
-- Jusqu'ici, `is_admin` donnait tout à tout le monde : n'importe quel
-- administrateur pouvait en promouvoir un autre et changer les coordonnées de
-- la boutique. Dans un commerce, celui qui tient la maison n'est pas celui à
-- qui il confie l'expédition des commandes.
--
-- Le drapeau existant reste le laissez-passer de l'administration ; celui-ci
-- désigne le seul compte qui puisse en distribuer d'autres et toucher aux
-- réglages. Deux colonnes plutôt qu'un rang en toutes lettres : tout le code
-- écrit autour de `is_admin` continue de fonctionner sans être relu.

ALTER TABLE customers
    ADD COLUMN is_super_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER is_admin;

-- Le compte fondateur est le plus ancien administrateur : c'est celui qui a été
-- promu depuis le serveur, avant qu'aucun autre ne puisse exister. Le faire
-- désigner par une adresse écrite ici aurait lié la migration à une
-- installation particulière.
UPDATE customers
   SET is_super_admin = 1
 WHERE is_admin = 1
 ORDER BY id
 LIMIT 1;
