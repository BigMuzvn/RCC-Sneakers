-- Identifiants auto-incrémentés sur le catalogue.
--
-- Les tables ont été créées sans AUTO_INCREMENT : le semoir fournissait les
-- identifiants lui-même, repris des modules TypeScript. C'était suffisant tant
-- que le catalogue ne se remplissait que par ce chemin.
--
-- Dès que l'administration crée un article, l'absence d'AUTO_INCREMENT insère
-- l'identifiant 0, et la création suivante heurte la clé primaire. Le semoir
-- continue de fonctionner : fournir un identifiant explicite reste permis, et
-- MySQL replace le compteur au-dessus de la plus grande valeur insérée.
--
-- La vérification des clés étrangères est suspendue le temps de l'opération :
-- MySQL refuse d'altérer une colonne référencée, alors que le type et la
-- valeur des identifiants ne changent pas.

DELETE FROM products WHERE id = 0;
DELETE FROM jerseys WHERE id = 0;

SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE products MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE jerseys  MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT;

SET FOREIGN_KEY_CHECKS = 1;
