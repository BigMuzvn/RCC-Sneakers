-- Administration.
--
-- Pas de second système d'authentification : un drapeau sur `customers`
-- réutilise sessions, limitation de débit, bcrypt et révocation, tous déjà
-- écrits et testés. Une authentification parallèle doublerait le code et la
-- surface à sécuriser pour une équipe de deux personnes.

ALTER TABLE customers
    ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER email_verified_at,
    -- 'active' | 'suspended' | 'anonymised'
    ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'active' AFTER is_admin,
    ADD COLUMN anonymised_at DATETIME NULL DEFAULT NULL AFTER status;

CREATE INDEX idx_customers_admin ON customers (is_admin);

-- Lettre d'information.
--
-- Les inscriptions vivent ici ET sont poussées dans une liste Brevo. Garder la
-- copie locale évite de dépendre d'un tiers pour savoir qui est inscrit, et
-- permet de rejouer la synchronisation si elle a échoué.
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email          VARCHAR(191) NOT NULL,
    status         VARCHAR(16)  NOT NULL DEFAULT 'subscribed',
    source         VARCHAR(32)  NOT NULL DEFAULT 'footer',
    synced_at      DATETIME     NULL DEFAULT NULL,
    created_at     DATETIME     NOT NULL,
    unsubscribed_at DATETIME    NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_newsletter_email (email),
    KEY idx_newsletter_status (status, created_at)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages du formulaire de contact.
CREATE TABLE IF NOT EXISTS contact_messages (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(120) NOT NULL,
    email       VARCHAR(191) NOT NULL,
    phone       VARCHAR(32)  NULL DEFAULT NULL,
    subject     VARCHAR(160) NOT NULL,
    body        TEXT         NOT NULL,
    status      VARCHAR(16)  NOT NULL DEFAULT 'new',
    created_at  DATETIME     NOT NULL,
    handled_at  DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_messages_status (status, created_at)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Réglages de la boutique.
--
-- Clé/valeur plutôt qu'une colonne par réglage : ce sont des textes que le
-- gérant modifie, pas un modèle de données. Y vivent les coordonnées affichées
-- en pied de page, l'adresse qui reçoit les commandes, et les quatre slugs de
-- la vitrine d'accueil — jusqu'ici écrits en dur dans le code.
--
-- La colonne s'appelle `name` et non `key` : `key` est un mot réservé de MySQL
-- et obligerait à l'échapper dans chaque requête.
CREATE TABLE IF NOT EXISTS settings (
    name       VARCHAR(64) NOT NULL,
    value      TEXT        NOT NULL,
    updated_at DATETIME    NOT NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des actions d'administration.
--
-- À deux personnes aux commandes, « qui a changé ce prix ? » et « qui a
-- anonymisé ce client ? » finissent toujours par se poser. Une ligne par
-- action coûte presque rien et rend la réponse possible.
CREATE TABLE IF NOT EXISTS admin_log (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id    INT UNSIGNED NULL DEFAULT NULL,
    admin_email VARCHAR(191) NOT NULL,
    action      VARCHAR(48)  NOT NULL,
    target      VARCHAR(120) NOT NULL,
    detail      TEXT         NULL DEFAULT NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_admin_log_date (created_at),
    KEY idx_admin_log_admin (admin_id, created_at),
    -- SET NULL et non CASCADE : l'historique doit survivre au départ de son
    -- auteur, d'où la copie de son adresse dans la ligne.
    CONSTRAINT fk_admin_log_admin FOREIGN KEY (admin_id)
        REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
