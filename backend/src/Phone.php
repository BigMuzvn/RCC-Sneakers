<?php

namespace Rcc;

/**
 * Normalisation des numéros de téléphone.
 *
 * Le téléphone est un identifiant de connexion de plein droit : à Cotonou
 * beaucoup de clients ont un numéro et pas d'adresse mail consultée. Il porte
 * donc une contrainte d'unicité, qui ne vaut que si toutes les écritures d'un
 * même numéro convergent vers une seule chaîne.
 *
 * Le plan de numérotation béninois exact — la longueur après la migration à dix
 * chiffres — reste à confirmer sur un numéro réel avant la mise en ligne. D'ici
 * là, la normalisation est délibérément tolérante plutôt que stricte : elle fait
 * converger les écritures sans juger de la validité du numéro. Une règle trop
 * stricte écrirait un rejet dans le code et refuserait de vrais clients.
 */
class Phone
{
    /** Bénin. */
    public const DEFAULT_COUNTRY = '229';

    /** E.164 plafonne à 15 chiffres ; en dessous de 11 il manque un indicatif. */
    private const MIN_DIGITS = 11;
    private const MAX_DIGITS = 15;

    public static function normalize(string $input, string $country = self::DEFAULT_COUNTRY): ?string
    {
        $input = trim($input);
        $digits = preg_replace('/\D+/', '', $input) ?? '';

        if ($digits === '') {
            return null;
        }

        // « +229… » et « 00229… » annoncent tous deux une forme internationale.
        $isInternational = str_starts_with($input, '+') || str_starts_with($digits, '00');

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Sans marqueur international, on préfixe l'indicatif local — sauf si le
        // client l'a déjà écrit sans le « + », cas fréquent à la saisie.
        if (!$isInternational && !str_starts_with($digits, $country)) {
            $digits = $country . $digits;
        }

        $length = strlen($digits);

        if ($length < self::MIN_DIGITS || $length > self::MAX_DIGITS) {
            return null;
        }

        return $digits;
    }
}
