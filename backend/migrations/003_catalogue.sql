-- Catalogue côté serveur.
--
-- Il vivait jusqu'ici dans les modules du front. Il doit passer en base pour une
-- raison qui ne se négocie pas : à la commande, le serveur ne peut pas faire
-- confiance aux prix envoyés par le navigateur — sans quoi n'importe qui
-- commande à 0 F. Le stock suit le même raisonnement : tant qu'il n'est connu
-- que du client, rien n'empêche de vendre deux fois la dernière paire.
--
-- Les identifiants sont ceux des modules TypeScript, délibérément conservés :
-- le panier et les favoris pointent déjà dessus.

CREATE TABLE IF NOT EXISTS products (
    id            INT UNSIGNED NOT NULL,
    slug          VARCHAR(191) NOT NULL,
    brand         VARCHAR(60)  NOT NULL,
    model         VARCHAR(120) NOT NULL,
    sku           VARCHAR(60)  NULL DEFAULT NULL,
    category      VARCHAR(32)  NOT NULL,
    gender        VARCHAR(16)  NOT NULL,
    colorway      VARCHAR(120) NOT NULL,
    description   TEXT         NOT NULL,
    price_xof     INT UNSIGNED NOT NULL,
    old_price_xof INT UNSIGNED NULL DEFAULT NULL,
    is_new_drop   TINYINT(1)   NOT NULL DEFAULT 0,
    -- Nom de fichier, pas une URL : côté front les assets sont des empreintes
    -- de build qui changent à chaque compilation.
    image         VARCHAR(191) NULL DEFAULT NULL,
    accent        VARCHAR(9)   NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL,
    updated_at    DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_variants (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    size       VARCHAR(8)   NOT NULL,
    stock      INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_variant (product_id, size),
    CONSTRAINT fk_product_variants_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jerseys (
    id            INT UNSIGNED NOT NULL,
    slug          VARCHAR(191) NOT NULL,
    club          VARCHAR(120) NOT NULL,
    league        VARCHAR(60)  NOT NULL,
    brand         VARCHAR(60)  NOT NULL,
    kit           VARCHAR(24)  NOT NULL,
    season        VARCHAR(16)  NOT NULL,
    colorway      VARCHAR(120) NOT NULL,
    price_xof     INT UNSIGNED NOT NULL,
    old_price_xof INT UNSIGNED NULL DEFAULT NULL,
    is_new_drop   TINYINT(1)   NOT NULL DEFAULT 0,
    image         VARCHAR(191) NULL DEFAULT NULL,
    accent        VARCHAR(9)   NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL,
    updated_at    DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_jerseys_slug (slug)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jersey_variants (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    jersey_id INT UNSIGNED NOT NULL,
    size      VARCHAR(8)   NOT NULL,
    stock     INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_jersey_variant (jersey_id, size),
    CONSTRAINT fk_jersey_variants_jersey FOREIGN KEY (jersey_id)
        REFERENCES jerseys (id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Zones de livraison. En base et non dans le code : le jour où les tarifs réels
-- des coursiers seront connus, c'est une mise à jour de données, pas un
-- redéploiement. Et surtout, le montant des frais devient inattaquable — le
-- navigateur ne peut pas envoyer « frais = 0 ».
CREATE TABLE IF NOT EXISTS delivery_zones (
    id          VARCHAR(32) NOT NULL,
    label       VARCHAR(80) NOT NULL,
    delay_label VARCHAR(40) NOT NULL,
    fee_xof     INT UNSIGNED NOT NULL,
    position    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_active   TINYINT(1)  NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
