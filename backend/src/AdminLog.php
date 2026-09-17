<?php

namespace Rcc;

/**
 * Journal des actions d'administration.
 *
 * À deux personnes aux commandes, « qui a changé ce prix ? » et « qui a
 * anonymisé ce client ? » finissent toujours par se poser. Une ligne par action
 * coûte presque rien et rend la réponse possible.
 *
 * L'adresse de l'administrateur est **recopiée** dans la ligne : le journal
 * doit rester lisible même si son auteur quitte la boutique et que son compte
 * disparaît.
 */
class AdminLog
{
    /** @param array<string,mixed> $admin ligne de `customers` */
    public static function record(array $admin, string $action, string $target, ?string $detail = null): void
    {
        Database::run(
            'INSERT INTO admin_log (admin_id, admin_email, action, target, detail, created_at)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                (int) $admin['id'],
                $admin['email'],
                $action,
                mb_substr($target, 0, 120),
                $detail === null ? null : mb_substr($detail, 0, 2000),
                Database::now(),
            ]
        );
    }

    /**
     * Décrit un changement en une ligne lisible : « prix 96 000 → 89 000 ».
     *
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     */
    public static function diff(array $before, array $after): ?string
    {
        $changes = [];

        foreach ($after as $field => $value) {
            $old = $before[$field] ?? null;

            // Comparaison souple : la base rend des chaînes là où le contrôleur
            // manipule des entiers, sans que rien n'ait changé pour autant.
            if ((string) $old === (string) $value) {
                continue;
            }

            $changes[] = sprintf('%s %s → %s', $field, self::short($old), self::short($value));
        }

        return $changes === [] ? null : implode(', ', $changes);
    }

    private static function short(mixed $value): string
    {
        if ($value === null) {
            return '∅';
        }

        if (is_bool($value)) {
            return $value ? 'oui' : 'non';
        }

        $text = (string) $value;

        return mb_strlen($text) > 60 ? mb_substr($text, 0, 57) . '…' : $text;
    }
}
