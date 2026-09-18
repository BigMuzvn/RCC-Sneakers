<?php

namespace Rcc\Admin;

use Rcc\AdminLog;
use Rcc\Database;
use Rcc\Request;
use Rcc\Response;
use Rcc\Settings;

/**
 * Réglages de la boutique, vitrine d'accueil et tarifs de livraison.
 *
 * Tout ce qui était écrit en dur et qu'il fallait un développeur pour changer.
 */
class SettingsController
{
    /**
     * Réglages modifiables, avec leur contrainte.
     *
     * La liste est fermée : sans elle, n'importe quelle clé pourrait être
     * écrite depuis le navigateur, y compris une clé inventée que plus rien ne
     * lirait jamais.
     *
     * @var array<string,array{label:string,type:string,max:int}>
     */
    private const EDITABLE = [
        'shop_notification_email' => ['label' => 'Adresse qui reçoit les commandes', 'type' => 'email', 'max' => 191],
        'shop_city' => ['label' => 'Ville affichée', 'type' => 'text', 'max' => 120],
        'shop_phone' => ['label' => 'Téléphone', 'type' => 'text', 'max' => 32],
        'shop_email' => ['label' => 'E-mail public', 'type' => 'email', 'max' => 191],
        'shop_hours' => ['label' => 'Horaires ou mention de retrait', 'type' => 'text', 'max' => 160],
        // Mentions portées sur la facture. Vides tant que la boutique n'a pas
        // communiqué ses numéros : une facture sans RCCM vaut mieux qu'une
        // facture avec un RCCM inventé.
        'shop_rccm' => ['label' => 'RCCM (facture)', 'type' => 'text', 'max' => 60],
        'shop_ifu' => ['label' => 'IFU (facture)', 'type' => 'text', 'max' => 60],
        'social_instagram' => ['label' => 'Instagram', 'type' => 'url', 'max' => 191],
        'social_facebook' => ['label' => 'Facebook', 'type' => 'url', 'max' => 191],
        'social_whatsapp' => ['label' => 'WhatsApp', 'type' => 'url', 'max' => 191],
    ];

    /** Nombre de paires mises en avant sur l'accueil. */
    private const FEATURED_COUNT = 4;

    /** @param array<string,mixed> $admin */
    public function index(Request $request, array $admin): Response
    {
        $featured = Settings::list('featured_slugs');

        // Les paires en vitrine sont renvoyées avec leur fiche : l'interface
        // doit pouvoir afficher de quoi il s'agit, pas une liste de slugs.
        $fiches = [];

        if ($featured !== []) {
            $placeholders = implode(',', array_fill(0, count($featured), '?'));

            $rows = Database::run(
                "SELECT slug, brand, model, colorway, image, accent, price_xof, is_active
                   FROM products WHERE slug IN ({$placeholders})",
                $featured
            )->fetchAll();

            $fiches = array_column($rows, null, 'slug');
        }

        return Response::data([
            'settings' => array_map(
                static fn (string $name) => [
                    'name' => $name,
                    'label' => self::EDITABLE[$name]['label'],
                    'type' => self::EDITABLE[$name]['type'],
                    'value' => Settings::get($name),
                ],
                array_keys(self::EDITABLE)
            ),

            'featured' => array_map(static function (string $slug) use ($fiches) {
                $fiche = $fiches[$slug] ?? null;

                return [
                    'slug' => $slug,
                    // Un slug qui ne correspond plus à rien doit se voir : sinon
                    // la vitrine perd une paire en silence.
                    'found' => $fiche !== null,
                    'active' => $fiche !== null && (bool) $fiche['is_active'],
                    'title' => $fiche === null ? null : $fiche['brand'] . ' ' . $fiche['model'],
                    'colorway' => $fiche['colorway'] ?? null,
                    'image' => $fiche['image'] ?? null,
                    'accent' => $fiche['accent'] ?? null,
                    'price_xof' => $fiche === null ? null : (int) $fiche['price_xof'],
                ];
            }, $featured),

            'featured_count' => self::FEATURED_COUNT,

            'delivery_zones' => array_map(static fn (array $z) => [
                'id' => $z['id'],
                'label' => $z['label'],
                'delay_label' => $z['delay_label'],
                'fee_xof' => (int) $z['fee_xof'],
                'is_active' => (bool) $z['is_active'],
            ], Database::run('SELECT * FROM delivery_zones ORDER BY position, id')->fetchAll()),
        ]);
    }

    /** @param array<string,mixed> $admin */
    public function update(Request $request, array $admin): Response
    {
        $errors = [];
        $changes = [];

        foreach (self::EDITABLE as $name => $regle) {
            $brut = $request->input($name);

            // Un réglage absent de la requête n'est pas vidé : on modifie ce
            // qu'on envoie, pas tout le formulaire.
            if ($brut === null) {
                continue;
            }

            $valeur = trim((string) $brut);

            if (mb_strlen($valeur) > $regle['max']) {
                $errors[$name] = sprintf('%d caractères maximum.', $regle['max']);

                continue;
            }

            // Les champs facultatifs peuvent être vidés — tout le monde n'a pas
            // de page Facebook.
            if ($valeur !== '') {
                if ($regle['type'] === 'email' && !filter_var($valeur, FILTER_VALIDATE_EMAIL)) {
                    $errors[$name] = "Cette adresse e-mail n'est pas valide.";

                    continue;
                }

                if ($regle['type'] === 'url' && !preg_match('#^https?://#i', $valeur)) {
                    $errors[$name] = 'Le lien doit commencer par https://';

                    continue;
                }
            }

            $ancien = Settings::get($name);

            if ($ancien !== $valeur) {
                Settings::set($name, $valeur);
                $changes[] = $name;
            }
        }

        if ($errors !== []) {
            return Response::validation($errors);
        }

        if ($changes !== []) {
            AdminLog::record($admin, 'settings.update', implode(', ', $changes));
        }

        return Response::data(['updated' => $changes]);
    }

    /**
     * Les quatre paires du carrousel d'accueil.
     *
     * @param array<string,mixed> $admin
     */
    public function updateFeatured(Request $request, array $admin): Response
    {
        $slugs = $request->input('slugs');

        if (!is_array($slugs)) {
            return Response::validation(['slugs' => 'Liste attendue.']);
        }

        $slugs = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $slugs
        ))));

        if (count($slugs) !== self::FEATURED_COUNT) {
            return Response::validation(['slugs' => sprintf(
                'Choisissez exactement %d paires, toutes différentes.',
                self::FEATURED_COUNT
            )]);
        }

        $placeholders = implode(',', array_fill(0, count($slugs), '?'));

        $connus = array_column(
            Database::run(
                "SELECT slug FROM products WHERE slug IN ({$placeholders}) AND is_active = 1",
                $slugs
            )->fetchAll(),
            'slug'
        );

        $inconnus = array_diff($slugs, $connus);

        if ($inconnus !== []) {
            // Une vitrine qui pointe vers un article retiré afficherait un vide
            // sur la page d'accueil, là où tout le monde regarde en premier.
            return Response::validation(['slugs' => sprintf(
                'Introuvable ou retiré de la vente : %s.',
                implode(', ', $inconnus)
            )]);
        }

        $avant = Settings::list('featured_slugs');
        Settings::set('featured_slugs', json_encode($slugs, JSON_UNESCAPED_SLASHES));

        AdminLog::record($admin, 'featured.update', 'accueil', sprintf(
            '%s → %s',
            implode(', ', $avant),
            implode(', ', $slugs)
        ));

        return Response::data(['slugs' => $slugs]);
    }

    /** @param array<string,mixed> $admin */
    public function updateZone(Request $request, array $admin, string $id): Response
    {
        $zone = Database::first('SELECT * FROM delivery_zones WHERE id = ?', [$id]);

        if ($zone === null) {
            return Response::notFound("Cette zone n'existe pas.");
        }

        $errors = [];

        $label = trim((string) $request->input('label', $zone['label']));
        $delai = trim((string) $request->input('delay_label', $zone['delay_label']));
        $frais = $request->input('fee_xof', $zone['fee_xof']);

        if (mb_strlen($label) < 2 || mb_strlen($label) > 80) {
            $errors['label'] = 'Entre 2 et 80 caractères.';
        }

        if (mb_strlen($delai) > 40) {
            $errors['delay_label'] = '40 caractères maximum.';
        }

        if (!is_numeric($frais) || (int) $frais < 0 || (int) $frais > 1_000_000) {
            $errors['fee_xof'] = 'Montant invalide.';
        }

        if ($errors !== []) {
            return Response::validation($errors);
        }

        $active = $request->input('is_active');
        $active = $active === null
            ? (int) $zone['is_active']
            : (in_array($active, [true, 1, '1', 'true'], true) ? 1 : 0);

        Database::run(
            'UPDATE delivery_zones SET label = ?, delay_label = ?, fee_xof = ?, is_active = ? WHERE id = ?',
            [$label, $delai, (int) $frais, $active, $id]
        );

        AdminLog::record($admin, 'zone.update', $id, AdminLog::diff(
            ['label' => $zone['label'], 'delay_label' => $zone['delay_label'], 'fee_xof' => $zone['fee_xof'], 'is_active' => $zone['is_active']],
            ['label' => $label, 'delay_label' => $delai, 'fee_xof' => (int) $frais, 'is_active' => $active]
        ));

        return Response::data([
            'zone' => [
                'id' => $id,
                'label' => $label,
                'delay_label' => $delai,
                'fee_xof' => (int) $frais,
                'is_active' => (bool) $active,
            ],
        ]);
    }
}
