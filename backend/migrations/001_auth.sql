-- Authentification. SQL délibérément conservateur : dev en MySQL 8.4, production
-- probable en 5.7 / MariaDB 10.x. Pas de CHECK (ignoré en 5.7), pas de CTE,
-- collation utf8mb4_unicode_ci et non utf8mb4_0900_ai_ci qui n'existe qu'en 8.0+.
--
-- Les colonnes indexées sont en VARCHAR(191) : en utf8mb4 un index sur 255
-- caractères pèse 1020 octets et dépasse la limite de 767 octets des anciennes
-- configurations InnoDB. 191 x 4 = 764, juste en dessous.

CREATE TABLE IF NOT EXISTS customers (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name              VARCHAR(120) NOT NULL,
    email             VARCHAR(191) NOT NULL,
    -- phone : forme canonique en chiffres seuls, sert l'unicité et la recherche.
    -- phone_display : forme saisie par le client, sert l'affichage.
    -- Sans cette séparation, « +229 01 97 00 00 00 » et « 0197000000 »
    -- créeraient deux comptes pour la même personne.
    phone             VARCHAR(32)  NOT NULL,
    phone_display     VARCHAR(32)  NOT NULL,
    password_hash     VARCHAR(255) NOT NULL,
    email_verified_at DATETIME     NULL DEFAULT NULL,
    created_at        DATETIME     NOT NULL,
    updated_at        DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_email (email),
    UNIQUE KEY uq_customers_phone (phone)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- « Se souvenir de moi », schéma sélecteur/validateur.
-- La base ne stocke que le SHA-256 de la moitié secrète : une fuite ne donne
-- aucune session utilisable, et chaque appareil reste révocable séparément.
CREATE TABLE IF NOT EXISTS auth_tokens (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id    INT UNSIGNED NOT NULL,
    selector       CHAR(32)     NOT NULL,
    validator_hash CHAR(64)     NOT NULL,
    expires_at     DATETIME     NOT NULL,
    created_at     DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_auth_tokens_selector (selector),
    KEY idx_auth_tokens_customer (customer_id),
    CONSTRAINT fk_auth_tokens_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vérification d'e-mail et réinitialisation de mot de passe : même mécanique,
-- donc une seule table. Jeton haché, usage unique.
CREATE TABLE IF NOT EXISTS customer_tokens (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    purpose     VARCHAR(32)  NOT NULL,
    token_hash  CHAR(64)     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL DEFAULT NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customer_tokens_hash (token_hash),
    KEY idx_customer_tokens_lookup (customer_id, purpose),
    CONSTRAINT fk_customer_tokens_customer FOREIGN KEY (customer_id)
        REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limitation de débit. Seules les tentatives qui consomment le quota sont
-- écrites ; une connexion réussie efface les lignes de l'identifiant.
-- L'identifiant est haché : ce journal ne doit pas devenir une liste en clair
-- des adresses de la clientèle, et l'index reste court.
CREATE TABLE IF NOT EXISTS auth_attempts (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    action          VARCHAR(32) NOT NULL,
    identifier_hash CHAR(64)    NOT NULL,
    ip              VARCHAR(45) NOT NULL,
    attempted_at    DATETIME    NOT NULL,
    PRIMARY KEY (id),
    KEY idx_attempts_identifier (action, identifier_hash, attempted_at),
    KEY idx_attempts_ip (action, ip, attempted_at)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
