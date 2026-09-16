-- Commandes.
--
-- Contrairement au panier et aux favoris, qui ne stockent que des identifiants
-- pour suivre le catalogue, une commande **fige ses données**. Le prix, le nom
-- et la taille sont recopiés au moment de l'achat : sinon changer un tarif
-- réécrit l'histoire, et une facture émise l'an dernier affiche le prix
-- d'aujourd'hui. C'est le point de conception central de cette table.

CREATE TABLE IF NOT EXISTS orders (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- Référence lisible, montrée au client et générée côté serveur.
    reference        VARCHAR(24)  NOT NULL,
    customer_id      INT UNSIGNED NOT NULL,
    status           VARCHAR(24)  NOT NULL DEFAULT 'pending',

    -- Coordonnées recopiées elles aussi : la livraison peut être adressée à
    -- quelqu'un d'autre, et une commande doit rester lisible même si le client
    -- change ensuite de nom ou de numéro.
    contact_name     VARCHAR(120) NOT NULL,
    contact_email    VARCHAR(191) NOT NULL,
    contact_phone    VARCHAR(32)  NOT NULL,

    delivery_zone    VARCHAR(32)  NOT NULL,
    delivery_label   VARCHAR(80)  NOT NULL,
    delivery_address VARCHAR(255) NOT NULL,

    payment_method   VARCHAR(24)  NOT NULL,
    payment_status   VARCHAR(24)  NOT NULL DEFAULT 'unpaid',

    subtotal_xof     INT UNSIGNED NOT NULL,
    delivery_fee_xof INT UNSIGNED NOT NULL,
    total_xof        INT UNSIGNED NOT NULL,

    created_at       DATETIME     NOT NULL,
    updated_at       DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_reference (reference),
    KEY idx_orders_customer (customer_id, created_at),
    -- RESTRICT et non CASCADE : une commande est une pièce comptable. Supprimer
    -- un compte ne doit pas effacer l'historique des ventes.
    CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE RESTRICT
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id       INT UNSIGNED NOT NULL,

    -- On garde le lien vers l'article pour pouvoir y retourner…
    item_type      VARCHAR(16)  NOT NULL,
    item_id        INT UNSIGNED NOT NULL,

    -- …mais tout ce qui suit est une copie figée, qui survivra à un changement
    -- de prix, de nom, ou au retrait pur et simple de l'article du catalogue.
    title          VARCHAR(191) NOT NULL,
    subtitle       VARCHAR(191) NOT NULL,
    size           VARCHAR(8)   NOT NULL,
    unit_price_xof INT UNSIGNED NOT NULL,
    qty            SMALLINT UNSIGNED NOT NULL,
    line_total_xof INT UNSIGNED NOT NULL,
    image          VARCHAR(191) NULL DEFAULT NULL,

    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id)
        REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
