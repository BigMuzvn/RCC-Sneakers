<?php

namespace Rcc\Admin;

use PDOException;
use Rcc\AdminLog;
use Rcc\Database;
use Rcc\ImageStore;
use Rcc\Request;
use Rcc\Response;
use Rcc\Validator;
use RuntimeException;

/**
 * Catalogue : créer, modifier, réapprovisionner, retirer.
 *
 * Sneakers et maillots partagent presque tout — seules quelques colonnes
 * diffèrent. Le code est donc écrit une fois et paramétré par un descripteur,
 * plutôt que dupliqué puis désynchronisé à la première correction.
 */
class CatalogueController
{
    /** Descripteurs des deux familles d'articles. */
    private const KINDS = [
        'product' => [
            'table' => 'products',
            'variants' => 'product_variants',
            'key' => 'product_id',
            'label' => 'Produit',
            'sizes' => ['39', '40', '41', '42', '43', '44', '45'],
            'fields' => ['brand', 'model', 'sku', 'category', 'gender', 'colorway', 'description'],
        ],
        'jersey' => [
            'table' => 'jerseys',
            'variants' => 'jersey_variants',
            'key' => 'jersey_id',
            'label' => 'Maillot',
            'sizes' => ['S', 'M', 'L', 'XL', 'XXL'],
            'fields' => ['club', 'league', 'brand', 'kit', 'season', 'colorway'],
        ],
    ];

    private const CATEGORIES = ['lifestyle', 'running', 'basketball', 'skate'];
    private const GENDERS = ['homme', 'femme', 'unisexe'];
    private const KITS = ['Domicile', 'Extérieur', 'Third'];

    // --------------------------------------------------------- lectures

    /** @param array<string,mixed> $admin */
    public function products(Request $request, array $admin): Response
    {
        return Response::data(['products' => $this->listOf('product', $request)]);
    }

    /** @param array<string,mixed> $admin */
    public function jerseys(Request $request, array $admin): Response
    {
        return Response::data(['jerseys' => $this->listOf('jersey', $request)]);
    }

    // --------------------------------------------------------- écritures

    /** @param array<string,mixed> $admin */
    public function createProduct(Request $request, array $admin): Response
    {
        return $this->create('product', $request, $admin);
    }

    /** @param array<string,mixed> $admin */
    public function createJersey(Request $request, array $admin): Response
    {
        return $this->create('jersey', $request, $admin);
    }

    /** @param array<string,mixed> $admin */
    public function updateProduct(Request $request, array $admin, string $id): Response
    {
        return $this->update('product', $request, $admin, (int) $id);
    }

    /** @param array<string,mixed> $admin */
    public function updateJersey(Request $request, array $admin, string $id): Response
    {
        return $this->update('jersey', $request, $admin, (int) $id);
    }

    /** @param array<string,mixed> $admin */
    public function updateProductStock(Request $request, array $admin, string $id): Response
    {
        return $this->updateStock('product', $request, $admin, (int) $id);
    }

    /** @param array<string,mixed> $admin */
    public function updateJerseyStock(Request $request, array $admin, string $id): Response
    {
        return $this->updateStock('jersey', $request, $admin, (int) $id);
    }

    // --------------------------------------------------------- visuels

    /** @param array<string,mixed> $admin */
    public function upload(Request $request, array $admin): Response
    {
        $file = $_FILES['image'] ?? null;

        if (!is_array($file)) {
            return Response::validation(['image' => 'Aucun fichier reçu.']);
        }

        // Contrôle fait ici et non dans ImageStore : il n'a de sens que sur une
        // vraie requête HTTP, et l'imposer plus bas rendrait le stockage
        // intestable. Il ferme la porte à un chemin local passé en douce.
        if (isset($file['tmp_name']) && $file['tmp_name'] !== '' && !is_uploaded_file($file['tmp_name'])) {
            return Response::validation(['image' => "Ce fichier n'a pas été téléversé."]);
        }

        try {
            $name = ImageStore::store($file);
        } catch (RuntimeException $e) {
            return Response::validation(['image' => $e->getMessage()]);
        }

        AdminLog::record($admin, 'image.upload', $name);

        return Response::data(['image' => $name], 201);
    }

    // ------------------------------------------------------------- privé

    /**
     * Traduit une violation d'intégrité en erreur de formulaire — ou rend null
     * si ce n'en est pas une, auquel cas l'exception doit remonter.
     *
     * Attribuer d'office tout code 23000 au slug a déjà masqué un vrai défaut :
     * les identifiants du catalogue n'étaient pas auto-incrémentés, chaque
     * création heurtait la clé primaire, et le message parlait d'une adresse
     * en double qui n'existait pas. Un message d'erreur qui devine fait perdre
     * plus de temps qu'il n'en fait gagner.
     *
     * @return array<string,string>|null
     */
    private function explainConflict(PDOException $e, string $table, string $slug, ?int $exceptId): ?array
    {
        if ($e->getCode() !== '23000') {
            return null;
        }

        $guard = $exceptId === null ? '' : ' AND id <> ?';
        $params = $exceptId === null ? [$slug] : [$slug, $exceptId];

        if (Database::first("SELECT id FROM {$table} WHERE slug = ?{$guard}", $params) !== null) {
            return ['slug' => 'Cette adresse est déjà utilisée par un autre article.'];
        }

        // Conflit réel mais pas sur le slug : on ne prétend pas savoir lequel.
        return ['form' => "L'enregistrement a été refusé par la base de données. Vérifiez les champs uniques."];
    }

    /** @return array<int,array<string,mixed>> */
    private function listOf(string $kind, Request $request): array
    {
        $k = self::KINDS[$kind];
        $where = [];
        $params = [];

        // Les articles retirés restent visibles à l'administration — sans quoi
        // on ne pourrait plus jamais en remettre un en vente.
        if ((string) $request->input('include_archived', '') !== '1') {
            $where[] = 'is_active = 1';
        }

        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            $colonnes = $kind === 'product'
                ? ['brand', 'model', 'colorway', 'sku', 'slug']
                : ['club', 'league', 'colorway', 'slug'];

            $where[] = '(' . implode(' OR ', array_map(static fn ($c) => "{$c} LIKE ?", $colonnes)) . ')';

            foreach ($colonnes as $ignored) {
                $params[] = '%' . $search . '%';
            }
        }

        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $rows = Database::run("SELECT * FROM {$k['table']}{$clause} ORDER BY id", $params)->fetchAll();
        $variants = $this->variantsOf($kind);

        return array_map(static function (array $row) use ($variants, $k) {
            $row['id'] = (int) $row['id'];
            $row['price_xof'] = (int) $row['price_xof'];
            $row['old_price_xof'] = $row['old_price_xof'] === null ? null : (int) $row['old_price_xof'];
            $row['is_new_drop'] = (bool) $row['is_new_drop'];
            $row['is_active'] = (bool) $row['is_active'];
            $row['variants'] = $variants[$row['id']] ?? [];
            $row['stock_total'] = array_sum(array_column($row['variants'], 'stock'));
            $row['sizes'] = $k['sizes'];

            return $row;
        }, $rows);
    }

    /** @return array<int,array<int,array{size:string,stock:int}>> */
    private function variantsOf(string $kind): array
    {
        $k = self::KINDS[$kind];

        $rows = Database::run(
            "SELECT {$k['key']} AS owner, size, stock FROM {$k['variants']} ORDER BY id"
        )->fetchAll();

        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row['owner']][] = ['size' => $row['size'], 'stock' => (int) $row['stock']];
        }

        return $grouped;
    }

    /** @param array<string,mixed> $admin */
    private function create(string $kind, Request $request, array $admin): Response
    {
        $k = self::KINDS[$kind];
        [$values, $errors] = $this->validate($kind, $request, null);

        if ($errors !== []) {
            return Response::validation($errors);
        }

        $colonnes = array_merge(['slug'], $k['fields'], ['price_xof', 'old_price_xof', 'is_new_drop', 'image', 'accent']);
        $placeholders = implode(', ', array_fill(0, count($colonnes) + 2, '?'));

        try {
            Database::run(
                sprintf(
                    'INSERT INTO %s (%s, created_at, updated_at) VALUES (%s)',
                    $k['table'],
                    implode(', ', $colonnes),
                    $placeholders
                ),
                array_merge(
                    array_map(static fn ($c) => $values[$c], $colonnes),
                    [Database::now(), Database::now()]
                )
            );
        } catch (PDOException $e) {
            $conflit = $this->explainConflict($e, $k['table'], $values['slug'], null);

            if ($conflit !== null) {
                return Response::validation($conflit);
            }

            throw $e;
        }

        $id = (int) Database::connection()->lastInsertId();

        // Toutes les tailles sont créées d'emblée, à zéro : sans cela, une
        // taille jamais saisie n'existerait pas et ne pourrait pas être
        // réapprovisionnée depuis la fiche.
        foreach ($k['sizes'] as $size) {
            Database::run(
                "INSERT IGNORE INTO {$k['variants']} ({$k['key']}, size, stock) VALUES (?, ?, 0)",
                [$id, $size]
            );
        }

        AdminLog::record($admin, $kind . '.create', $values['slug']);

        return Response::data(['id' => $id, 'slug' => $values['slug']], 201);
    }

    /** @param array<string,mixed> $admin */
    private function update(string $kind, Request $request, array $admin, int $id): Response
    {
        $k = self::KINDS[$kind];
        $existant = Database::first("SELECT * FROM {$k['table']} WHERE id = ?", [$id]);

        if ($existant === null) {
            return Response::notFound("Cet article n'existe pas.");
        }

        [$values, $errors] = $this->validate($kind, $request, $id);

        if ($errors !== []) {
            return Response::validation($errors);
        }

        $values['is_active'] = $this->flag($request, 'is_active', (bool) $existant['is_active']) ? 1 : 0;

        // Le visuel remplacé est effacé du disque : sans cela chaque correction
        // laisserait un fichier orphelin que plus rien ne référence.
        $ancienVisuel = $existant['image'];

        $colonnes = array_merge(['slug'], $k['fields'], [
            'price_xof', 'old_price_xof', 'is_new_drop', 'image', 'accent', 'is_active',
        ]);

        try {
            Database::run(
                sprintf(
                    'UPDATE %s SET %s, updated_at = ? WHERE id = ?',
                    $k['table'],
                    implode(', ', array_map(static fn ($c) => "{$c} = ?", $colonnes))
                ),
                array_merge(
                    array_map(static fn ($c) => $values[$c], $colonnes),
                    [Database::now(), $id]
                )
            );
        } catch (PDOException $e) {
            $conflit = $this->explainConflict($e, $k['table'], $values['slug'], $id);

            if ($conflit !== null) {
                return Response::validation($conflit);
            }

            throw $e;
        }

        if ($ancienVisuel !== null && $ancienVisuel !== $values['image'] && str_ends_with((string) $ancienVisuel, '.webp')) {
            ImageStore::delete($ancienVisuel);
        }

        $detail = AdminLog::diff(
            array_intersect_key($existant, array_flip($colonnes)),
            array_intersect_key($values, array_flip($colonnes))
        );

        AdminLog::record($admin, $kind . '.update', $values['slug'], $detail);

        return Response::data(['id' => $id, 'slug' => $values['slug']]);
    }

    /** @param array<string,mixed> $admin */
    private function updateStock(string $kind, Request $request, array $admin, int $id): Response
    {
        $k = self::KINDS[$kind];
        $article = Database::first("SELECT slug FROM {$k['table']} WHERE id = ?", [$id]);

        if ($article === null) {
            return Response::notFound("Cet article n'existe pas.");
        }

        $stocks = $request->input('stock');

        if (!is_array($stocks) || $stocks === []) {
            return Response::validation(['stock' => 'Aucune quantité reçue.']);
        }

        $changes = [];

        foreach ($stocks as $size => $quantity) {
            $size = (string) $size;

            if (!in_array($size, $k['sizes'], true)) {
                return Response::validation(['stock' => sprintf('Taille inconnue : %s.', $size)]);
            }

            if (!is_numeric($quantity) || (int) $quantity < 0 || (int) $quantity > 9999) {
                return Response::validation(['stock' => sprintf('Quantité invalide pour la taille %s.', $size)]);
            }

            $avant = Database::first(
                "SELECT stock FROM {$k['variants']} WHERE {$k['key']} = ? AND size = ?",
                [$id, $size]
            );

            Database::run(
                "INSERT INTO {$k['variants']} ({$k['key']}, size, stock) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE stock = VALUES(stock)",
                [$id, $size, (int) $quantity]
            );

            if ($avant !== null && (int) $avant['stock'] !== (int) $quantity) {
                $changes[] = sprintf('%s: %d → %d', $size, (int) $avant['stock'], (int) $quantity);
            }
        }

        if ($changes !== []) {
            AdminLog::record($admin, $kind . '.stock', $article['slug'], implode(', ', $changes));
        }

        $variants = $this->variantsOf($kind)[$id] ?? [];

        return Response::data([
            'variants' => $variants,
            'stock_total' => array_sum(array_column($variants, 'stock')),
        ]);
    }

    /**
     * Valide et normalise les champs communs et propres à chaque famille.
     *
     * @return array{0:array<string,mixed>,1:array<string,string>}
     */
    private function validate(string $kind, Request $request, ?int $exceptId): array
    {
        $k = self::KINDS[$kind];
        $v = new Validator($request->body);
        $values = [];

        foreach ($k['fields'] as $field) {
            $values[$field] = match ($field) {
                'sku' => trim((string) $request->input('sku', '')) ?: null,
                'description' => $v->text('description', 20, 2000),
                'category' => $this->choice($v, $request, 'category', self::CATEGORIES),
                'gender' => $this->choice($v, $request, 'gender', self::GENDERS),
                'kit' => $this->choice($v, $request, 'kit', self::KITS),
                'season' => $v->text('season', 4, 16),
                default => $v->text($field, 2, 120),
            };
        }

        $values['slug'] = $this->slug($v, $request, $k['table'], $exceptId, $values, $kind);
        $values['price_xof'] = $this->money($v, $request, 'price_xof', true);
        $values['old_price_xof'] = $this->money($v, $request, 'old_price_xof', false);
        $values['is_new_drop'] = $this->flag($request, 'is_new_drop', false) ? 1 : 0;
        $values['accent'] = $this->accent($v, $request);

        $image = trim((string) $request->input('image', ''));
        $values['image'] = $image === '' ? null : basename($image);

        // Un prix barré inférieur au prix courant afficherait une remise
        // négative sur les cartes.
        if ($values['old_price_xof'] !== null && $values['old_price_xof'] <= $values['price_xof']) {
            $v->addError('old_price_xof', 'Le prix barré doit être supérieur au prix de vente.');
        }

        return [$values, $v->errors()];
    }

    /** @param array<int,string> $allowed */
    private function choice(Validator $v, Request $request, string $field, array $allowed): string
    {
        $value = trim((string) $request->input($field, ''));

        if (!in_array($value, $allowed, true)) {
            $v->addError($field, 'Valeur attendue : ' . implode(', ', $allowed) . '.');

            return $allowed[0];
        }

        return $value;
    }

    private function money(Validator $v, Request $request, string $field, bool $required): ?int
    {
        $raw = $request->input($field);

        if ($raw === null || $raw === '' ) {
            if ($required) {
                $v->addError($field, 'Ce champ est obligatoire.');
            }

            return $required ? 0 : null;
        }

        if (!is_numeric($raw) || (int) $raw < 0 || (int) $raw > 100_000_000) {
            $v->addError($field, 'Montant invalide.');

            return $required ? 0 : null;
        }

        return (int) $raw;
    }

    private function accent(Validator $v, Request $request): string
    {
        $value = strtoupper(trim((string) $request->input('accent', '')));

        if (!preg_match('/^#[0-9A-F]{6}$/', $value)) {
            $v->addError('accent', 'Couleur attendue au format #RRGGBB.');

            return '#808080';
        }

        return $value;
    }

    /** @param array<string,mixed> $values */
    private function slug(Validator $v, Request $request, string $table, ?int $exceptId, array $values, string $kind): string
    {
        $raw = trim((string) $request->input('slug', ''));

        // Sans saisie, l'adresse se déduit du nom : le gérant n'a pas à savoir
        // ce qu'est un slug pour créer un produit.
        if ($raw === '') {
            $raw = $kind === 'product'
                ? sprintf('%s %s %s', $values['brand'] ?? '', $values['model'] ?? '', $values['colorway'] ?? '')
                : sprintf('%s %s %s', $values['club'] ?? '', $values['kit'] ?? '', $values['season'] ?? '');
        }

        $slug = self::slugify($raw);

        if ($slug === '') {
            $v->addError('slug', "Impossible de déduire une adresse : renseignez-la.");

            return '';
        }

        $guard = $exceptId === null ? '' : ' AND id <> ?';
        $params = $exceptId === null ? [$slug] : [$slug, $exceptId];

        if (Database::first("SELECT id FROM {$table} WHERE slug = ?{$guard}", $params) !== null) {
            $v->addError('slug', 'Cette adresse est déjà utilisée par un autre article.');
        }

        return $slug;
    }

    /**
     * Table de translittération, plutôt que `Normalizer` ou `iconv`.
     *
     * L'extension intl est absente ici et sur beaucoup de mutualisés, et
     * `iconv //TRANSLIT` rend des résultats qui dépendent de la locale du
     * serveur — « é » peut y devenir « 'e ». Une table explicite donne le même
     * résultat partout, ce qui compte pour des adresses qui finiront en lien.
     *
     * @var array<string,string>
     */
    private const TRANSLITERATION = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
        'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'œ' => 'oe',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y',
        'ß' => 'ss', 'đ' => 'd', 'ð' => 'd', 'þ' => 'th',
    ];

    public static function slugify(string $text): string
    {
        $text = strtr(mb_strtolower(trim($text)), self::TRANSLITERATION);
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);

        return trim($text, '-');
    }

    private function flag(Request $request, string $field, bool $default): bool
    {
        $value = $request->input($field);

        if ($value === null) {
            return $default;
        }

        return in_array($value, [true, 1, '1', 'true', 'on'], true);
    }
}
