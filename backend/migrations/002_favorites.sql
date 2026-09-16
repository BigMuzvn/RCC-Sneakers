-- Favoris.
--
-- Comme le panier, cette table ne stocke que des identifiants : le catalogue
-- vit dans les modules du front, et les URL d'images sont des empreintes de
-- build qui changent à chaque compilation. Dupliquer ici le nom ou le prix
-- d'une paire créerait deux vérités qui divergeraient au premier changement
-- de tarif.

CREATE TABLE IF NOT EXISTS favorites (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    item_type   VARCHAR(16)  NOT NULL,
    item_id     INT UNSIGNED NOT NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_favorites (customer_id, item_type, item_id),
    KEY idx_favorites_customer (customer_id, created_at),
    CONSTRAINT fk_favorites_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
