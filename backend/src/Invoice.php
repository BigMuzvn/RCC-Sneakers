<?php

namespace Rcc;

/**
 * La facture d'une commande, en PDF.
 *
 * Elle est **recomposée à la demande à partir de la commande enregistrée**, et
 * non stockée. Les lignes d'une commande sont figées en base au moment de la
 * validation — titre, taille, prix unitaire — donc rejouer le document dans
 * trois ans donnera exactement le même papier, même si la paire a changé de
 * prix ou disparu du catalogue. Stocker un fichier n'apporterait rien et
 * ajouterait un dossier à sauvegarder.
 *
 * Les mentions légales viennent des réglages et **ne sont imprimées que si
 * elles sont renseignées**. Un RCCM inventé sur un document comptable est pire
 * que pas de RCCM du tout.
 */
class Invoice
{
    private const MARGE = 18.0;
    private const DROITE = Pdf::A4_WIDTH - self::MARGE;

    private const ENCRE = '#17191C';
    private const GRIS = '#6B7280';
    private const TRAIT = '#D4D7DC';

    /**
     * Colonnes du tableau, en millimètres depuis le bord gauche.
     *
     * Les deux dernières sont des bords **droits** : les montants s'alignent par
     * la fin, sans quoi une ligne à 96 000 et une à 107 500 ne se comparent pas
     * d'un coup d'œil. L'écart entre elles tient la largeur du plus grand
     * montant plausible, suffixe compris.
     */
    private const COL_TAILLE = 112.0;
    private const COL_QTE_FIN = 134.0;
    private const COL_PU_FIN = 160.0;

    private const MOIS = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    private const PAIEMENTS = [
        'cash' => 'À la livraison, en espèces',
        'online' => 'En ligne',
    ];

    private const STATUTS = [
        'pending' => 'En préparation',
        'confirmed' => 'Confirmée',
        'shipped' => 'Expédiée',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée',
    ];

    /**
     * @param array<string,mixed> $order tel que le renvoie OrdersController
     */
    public static function render(array $order): string
    {
        $pdf = new Pdf();
        $y = self::entete($pdf, $order);
        $y = self::parties($pdf, $order, $y);
        $y = self::lignes($pdf, $order, $y);
        $y = self::totaux($pdf, $order, $y);

        self::pied($pdf, $order, $y);

        return $pdf->output();
    }

    /** Nom du fichier proposé au téléchargement. */
    public static function filename(array $order): string
    {
        return 'facture-' . strtolower((string) $order['reference']) . '.pdf';
    }

    // ------------------------------------------------------------ blocs

    private static function entete(Pdf $pdf, array $order): float
    {
        $pdf->text('RCC', self::MARGE, 24, 26, true, self::ENCRE);
        $pdf->text('SNEAKERS', self::MARGE + 24.5, 24, 9, false, self::GRIS);

        $pdf->textRight('FACTURE', self::DROITE, 18, 13, true, self::ENCRE);
        $pdf->textRight((string) $order['reference'], self::DROITE, 24, 10, false, self::ENCRE);
        $pdf->textRight(self::date((string) $order['created_at']), self::DROITE, 29, 9, false, self::GRIS);

        $pdf->line(self::MARGE, 33, self::DROITE, 33, 0.35, self::ENCRE);

        return 44.0;
    }

    private static function parties(Pdf $pdf, array $order, float $y): float
    {
        $milieu = self::MARGE + 88;

        $pdf->text('VENDEUR', self::MARGE, $y, 7.5, true, self::GRIS);
        $pdf->text('CLIENT', $milieu, $y, 7.5, true, self::GRIS);

        $vendeur = array_values(array_filter([
            'RCC Sneakers',
            Settings::get('shop_city'),
            Settings::get('shop_phone'),
            Settings::get('shop_email'),
            self::mention('RCCM', Settings::get('shop_rccm')),
            self::mention('IFU', Settings::get('shop_ifu')),
        ], static fn (string $ligne) => $ligne !== ''));

        $client = array_values(array_filter([
            (string) $order['contact_name'],
            (string) $order['contact_phone'],
            (string) $order['contact_email'],
            (string) $order['delivery_label'],
            (string) $order['delivery_address'],
        ], static fn (string $ligne) => $ligne !== ''));

        $ligne = $y + 6;

        foreach ($vendeur as $index => $texte) {
            $pdf->text($pdf->truncate($texte, 80, 9), self::MARGE, $ligne + $index * 4.6, 9, $index === 0, self::ENCRE);
        }

        foreach ($client as $index => $texte) {
            $pdf->text($pdf->truncate($texte, 80, 9), $milieu, $ligne + $index * 4.6, 9, $index === 0, self::ENCRE);
        }

        $hauteur = max(count($vendeur), count($client)) * 4.6;

        return $ligne + $hauteur + 8;
    }

    /** Dernière ordonnée où une ligne d'article peut commencer. */
    private const BAS_TABLEAU = 232.0;

    private static function enteteTableau(Pdf $pdf, float $y): float
    {
        $pdf->rect(self::MARGE, $y - 4.5, self::DROITE - self::MARGE, 7, '#F2F3F5');

        $pdf->text('ARTICLE', self::MARGE + 2, $y, 7.5, true, self::GRIS);
        $pdf->text('TAILLE', self::COL_TAILLE, $y, 7.5, true, self::GRIS);
        $pdf->textRight('QTÉ', self::COL_QTE_FIN, $y, 7.5, true, self::GRIS);
        $pdf->textRight('P.U.', self::COL_PU_FIN, $y, 7.5, true, self::GRIS);
        $pdf->textRight('TOTAL', self::DROITE - 2, $y, 7.5, true, self::GRIS);

        return $y + 9;
    }

    private static function lignes(Pdf $pdf, array $order, float $y): float
    {
        $y = self::enteteTableau($pdf, $y);

        foreach ($order['items'] as $item) {
            // Une commande de soixante lignes est permise par le panier. Sans
            // ce report, ses derniers articles s'imprimeraient hors de la page
            // — c'est-à-dire nulle part.
            if ($y > self::BAS_TABLEAU) {
                $pdf->newPage();
                $pdf->text('RCC SNEAKERS', self::MARGE, 18, 10, true, self::ENCRE);
                $pdf->textRight((string) $order['reference'] . ' — suite', self::DROITE, 18, 9, false, self::GRIS);
                $pdf->line(self::MARGE, 22, self::DROITE, 22, 0.35, self::ENCRE);

                $y = self::enteteTableau($pdf, 33);
            }

            // Le sous-titre descend sous le titre : coloris et saison sont
            // utiles pour reconnaître l'article, mais ne doivent pas concurrencer
            // le nom dans la colonne.
            $largeur = self::COL_TAILLE - self::MARGE - 6;

            $pdf->text($pdf->truncate((string) $item['title'], $largeur, 9.5, true), self::MARGE + 2, $y, 9.5, true, self::ENCRE);

            if (($item['subtitle'] ?? '') !== '') {
                $pdf->text($pdf->truncate((string) $item['subtitle'], $largeur, 8), self::MARGE + 2, $y + 4, 8, false, self::GRIS);
            }

            $pdf->text((string) $item['size'], self::COL_TAILLE, $y, 9.5, false, self::ENCRE);
            $pdf->textRight((string) $item['qty'], self::COL_QTE_FIN, $y, 9.5, false, self::ENCRE);

            // Le prix unitaire se passe du suffixe : la devise est dite une fois
            // dans les totaux et une fois en pied de page. Répétée sur chaque
            // ligne, elle double la largeur de la colonne sans rien apprendre.
            $pdf->textRight(self::nombre((int) $item['unit_price_xof']), self::COL_PU_FIN, $y, 9.5, false, self::GRIS);
            $pdf->textRight(self::xof((int) $item['line_total_xof']), self::DROITE - 2, $y, 9.5, false, self::ENCRE);

            $y += (($item['subtitle'] ?? '') !== '') ? 11 : 8;

            $pdf->line(self::MARGE, $y - 3, self::DROITE, $y - 3, 0.15, self::TRAIT);
        }

        return $y + 4;
    }

    private static function totaux(Pdf $pdf, array $order, float $y): float
    {
        $gauche = self::DROITE - 62;

        $pdf->text('Sous-total', $gauche, $y, 9, false, self::GRIS);
        $pdf->textRight(self::xof((int) $order['subtotal_xof']), self::DROITE - 2, $y, 9, false, self::ENCRE);

        $pdf->text('Livraison — ' . $order['delivery_label'], $gauche, $y + 5.5, 9, false, self::GRIS);
        $pdf->textRight(self::xof((int) $order['delivery_fee_xof']), self::DROITE - 2, $y + 5.5, 9, false, self::ENCRE);

        $pdf->line($gauche, $y + 9, self::DROITE, $y + 9, 0.3, self::ENCRE);

        $pdf->text('TOTAL', $gauche, $y + 15, 11, true, self::ENCRE);
        $pdf->textRight(self::xof((int) $order['total_xof']), self::DROITE - 2, $y + 15, 13, true, self::ENCRE);

        return $y + 26;
    }

    private static function pied(Pdf $pdf, array $order, float $y): void
    {
        $paiement = self::PAIEMENTS[$order['payment_method']] ?? (string) $order['payment_method'];
        $regle = ($order['payment_status'] ?? '') === 'paid';

        $pdf->text('Paiement', self::MARGE, $y, 7.5, true, self::GRIS);
        $pdf->text($paiement, self::MARGE, $y + 5, 9.5, false, self::ENCRE);
        $pdf->text(
            $regle ? 'Réglée' : 'En attente de règlement',
            self::MARGE,
            $y + 10,
            9.5,
            true,
            $regle ? '#2F7D4F' : '#B4700F'
        );

        $pdf->text('Commande', self::MARGE + 88, $y, 7.5, true, self::GRIS);
        $pdf->text(
            self::STATUTS[$order['status']] ?? (string) $order['status'],
            self::MARGE + 88,
            $y + 5,
            9.5,
            false,
            self::ENCRE
        );

        // Le bas de page est ancré à la page, pas à la fin du tableau : une
        // facture d'un article et une facture de six doivent se ressembler.
        $bas = Pdf::A4_HEIGHT - self::MARGE;

        $pdf->line(self::MARGE, $bas - 12, self::DROITE, $bas - 12, 0.15, self::TRAIT);
        $pdf->text('Merci de votre confiance. RCC Sneakers — ' . Settings::get('shop_city'), self::MARGE, $bas - 7, 8, false, self::GRIS);
        $pdf->textRight('Montants en francs CFA (XOF)', self::DROITE, $bas - 7, 8, false, self::GRIS);
    }

    // ----------------------------------------------------------- outils

    private static function mention(string $etiquette, string $valeur): string
    {
        return $valeur === '' ? '' : "{$etiquette} : {$valeur}";
    }

    private static function nombre(int $montant): string
    {
        return number_format($montant, 0, ',', ' ');
    }

    private static function xof(int $montant): string
    {
        return self::nombre($montant) . ' F CFA';
    }

    /** Les dates sont enregistrées en UTC ; Cotonou est à UTC+1. */
    private static function date(string $utc): string
    {
        $time = strtotime($utc . ' UTC');

        if ($time === false) {
            return $utc;
        }

        $time += 3600;

        return sprintf('%d %s %d', (int) gmdate('j', $time), self::MOIS[(int) gmdate('n', $time)], (int) gmdate('Y', $time));
    }
}
