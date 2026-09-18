<?php

namespace Rcc;

use RuntimeException;

/**
 * Générateur de PDF minimal.
 *
 * Écrire un PDF à la main n'est pas un exercice de style : c'est la seule façon
 * de tenir la promesse du projet — **aucune dépendance en production**. FPDF ou
 * TCPDF imposeraient un `vendor/` à déployer, et la facture est le seul
 * document que la boutique produise. Ce dont elle a besoin tient en trois
 * primitives : du texte, des traits, des aplats.
 *
 * Deux choix méritent d'être dits :
 *
 *  - **Polices standard, jamais embarquées.** Helvetica fait partie des
 *    quatorze fontes que tout lecteur PDF possède. Embarquer un fichier de
 *    police alourdirait chaque facture de plusieurs centaines de kilo-octets
 *    pour un gain nul ici.
 *  - **Encodage WinAnsi.** C'est ce que comprennent les fontes standard. Le
 *    texte arrive en UTF-8 et est converti ; les caractères hors de cette table,
 *    comme l'espace fine insécable des milliers, sont remplacés plutôt que de
 *    ressortir en mojibake au milieu d'un montant.
 *
 * Les coordonnées sont exprimées **depuis le haut de la page**, en millimètres.
 * Le PDF compte en points depuis le bas ; y penser à chaque ligne d'une facture
 * serait une source d'erreurs constante.
 */
class Pdf
{
    /** 1 mm en points typographiques. */
    private const MM = 2.834645669;

    /** A4 en millimètres. */
    public const A4_WIDTH = 210.0;
    public const A4_HEIGHT = 297.0;

    /** @var array<int,string> flux de contenu, un par page */
    private array $pages = [];

    private string $content = '';

    public function __construct(
        private readonly float $width = self::A4_WIDTH,
        private readonly float $height = self::A4_HEIGHT,
    ) {
    }

    // ------------------------------------------------------------ dessin

    /**
     * Écrit une ligne de texte, coin haut-gauche de la ligne de base.
     *
     * @param string $color couleur hexadécimale « #RRGGBB »
     */
    public function text(
        string $value,
        float $x,
        float $y,
        float $size = 10,
        bool $bold = false,
        string $color = '#000000',
    ): void {
        if ($value === '') {
            return;
        }

        $this->content .= sprintf(
            "BT %s /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $this->fillColor($color),
            $bold ? 'F2' : 'F1',
            $size,
            $this->pt($x),
            $this->pty($y),
            $this->escape($value)
        );
    }

    /** Aligne la fin du texte sur `$right`. Indispensable pour une colonne de montants. */
    public function textRight(
        string $value,
        float $right,
        float $y,
        float $size = 10,
        bool $bold = false,
        string $color = '#000000',
    ): void {
        $this->text($value, $right - $this->widthOf($value, $size, $bold), $y, $size, $bold, $color);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $thickness = 0.2, string $color = '#000000'): void
    {
        $this->content .= sprintf(
            "%s %.2F w %.2F %.2F m %.2F %.2F l S\n",
            $this->strokeColor($color),
            $thickness * self::MM,
            $this->pt($x1),
            $this->pty($y1),
            $this->pt($x2),
            $this->pty($y2)
        );
    }

    /** Aplat plein, coin haut-gauche. */
    public function rect(float $x, float $y, float $width, float $height, string $color): void
    {
        $this->content .= sprintf(
            "%s %.2F %.2F %.2F %.2F re f\n",
            $this->fillColor($color),
            $this->pt($x),
            $this->pty($y + $height),
            $this->pt($width),
            $this->pt($height)
        );
    }

    /**
     * Largeur d'un texte, en millimètres.
     *
     * Les fontes standard ont des largeurs fixées par leur table AFM. Un
     * caractère accentué y occupe exactement la largeur de sa lettre de base —
     * « é » vaut « e » — ce qui permet de ne porter que la table ASCII.
     */
    public function widthOf(string $value, float $size, bool $bold = false): float
    {
        $table = $bold ? self::WIDTHS_BOLD : self::WIDTHS;
        $total = 0;

        foreach (str_split($this->toWinAnsi($value)) as $char) {
            $code = ord($char);
            $base = self::BASE_LETTER[$code] ?? $char;
            $total += $table[$base] ?? 556;
        }

        // Les largeurs AFM sont exprimées en millièmes de corps.
        return ($total / 1000) * $size / self::MM;
    }

    /** Coupe un texte à la largeur disponible, en ajoutant une ellipse. */
    public function truncate(string $value, float $maxWidth, float $size, bool $bold = false): string
    {
        if ($this->widthOf($value, $size, $bold) <= $maxWidth) {
            return $value;
        }

        // On coupe sur les caractères UTF-8 et non sur les octets, sinon un mot
        // terminé par un accent produirait un octet orphelin.
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sortie = '';

        foreach ($chars as $char) {
            if ($this->widthOf($sortie . $char . '…', $size, $bold) > $maxWidth) {
                break;
            }

            $sortie .= $char;
        }

        return rtrim($sortie) . '…';
    }

    public function newPage(): void
    {
        $this->pages[] = $this->content;
        $this->content = '';
    }

    // ----------------------------------------------------------- sortie

    /** Assemble le document. Les décalages de l'index sont comptés en octets. */
    public function output(): string
    {
        $pages = $this->pages;

        if ($this->content !== '' || $pages === []) {
            $pages[] = $this->content;
        }

        $objects = [];

        // 1 catalogue, 2 arbre des pages, 3 et 4 les fontes, puis deux objets
        // par page : la page elle-même et son flux.
        $kids = [];

        foreach (array_keys($pages) as $index) {
            $kids[] = sprintf('%d 0 R', 5 + $index * 2);
        }

        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = sprintf(
            "<< /Type /Pages /Kids [%s] /Count %d >>",
            implode(' ', $kids),
            count($pages)
        );
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";

        foreach ($pages as $index => $flux) {
            $page = 5 + $index * 2;

            $objects[$page] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] "
                    . "/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>",
                $this->width * self::MM,
                $this->height * self::MM,
                $page + 1
            );

            $objects[$page + 1] = sprintf(
                "<< /Length %d >>\nstream\n%s\nendstream",
                strlen($flux) + 1,
                $flux
            );
        }

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $count = count($objects) + 1;

        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";

        for ($id = 1; $id < $count; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }

        $pdf .= sprintf(
            "trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF",
            $count,
            $xref
        );

        return $pdf;
    }

    // ------------------------------------------------------------ outils

    private function pt(float $mm): float
    {
        return $mm * self::MM;
    }

    /** Ordonnée depuis le haut, convertie en coordonnée PDF depuis le bas. */
    private function pty(float $mm): float
    {
        return ($this->height - $mm) * self::MM;
    }

    private function fillColor(string $hex): string
    {
        [$r, $g, $b] = $this->rgb($hex);

        return sprintf('%.3F %.3F %.3F rg', $r, $g, $b);
    }

    private function strokeColor(string $hex): string
    {
        [$r, $g, $b] = $this->rgb($hex);

        return sprintf('%.3F %.3F %.3F RG', $r, $g, $b);
    }

    /** @return array{float,float,float} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            throw new RuntimeException("Couleur invalide : {$hex}");
        }

        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    /** Échappe ce qui a un sens dans une chaîne PDF. */
    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], $this->toWinAnsi($value));
    }

    /**
     * UTF-8 vers WinAnsi.
     *
     * Les espaces insécables — dont la fine que la typographie française met
     * entre les milliers — sont ramenées à une espace ordinaire avant la
     * conversion : elles n'existent pas dans cette table et ressortiraient en
     * point d'interrogation au milieu d'un montant.
     */
    private function toWinAnsi(string $value): string
    {
        // Apostrophes courbes, tirets cadratins et points de suspension existent
        // dans WinAnsi : on les garde. Seules les espaces insécables — dont la
        // fine que la typographie française met entre les milliers — n'y sont
        // pas, et ressortiraient en point d'interrogation au milieu d'un montant.
        $value = str_replace(["\u{202F}", "\u{00A0}", "\u{2009}", "\u{2007}"], ' ', $value);

        return mb_convert_encoding($value, 'Windows-1252', 'UTF-8');
    }

    /**
     * Lettre de base d'un caractère accentué WinAnsi, pour la largeur.
     *
     * @var array<int,string>
     */
    private const BASE_LETTER = [
        0xC0 => 'A', 0xC1 => 'A', 0xC2 => 'A', 0xC3 => 'A', 0xC4 => 'A', 0xC5 => 'A',
        0xC7 => 'C', 0xC8 => 'E', 0xC9 => 'E', 0xCA => 'E', 0xCB => 'E',
        0xCC => 'I', 0xCD => 'I', 0xCE => 'I', 0xCF => 'I',
        0xD1 => 'N', 0xD2 => 'O', 0xD3 => 'O', 0xD4 => 'O', 0xD5 => 'O', 0xD6 => 'O',
        0xD9 => 'U', 0xDA => 'U', 0xDB => 'U', 0xDC => 'U', 0xDD => 'Y',
        0xE0 => 'a', 0xE1 => 'a', 0xE2 => 'a', 0xE3 => 'a', 0xE4 => 'a', 0xE5 => 'a',
        0xE7 => 'c', 0xE8 => 'e', 0xE9 => 'e', 0xEA => 'e', 0xEB => 'e',
        0xEC => 'i', 0xED => 'i', 0xEE => 'i', 0xEF => 'i',
        0xF1 => 'n', 0xF2 => 'o', 0xF3 => 'o', 0xF4 => 'o', 0xF5 => 'o', 0xF6 => 'o',
        0xF9 => 'u', 0xFA => 'u', 0xFB => 'u', 0xFC => 'u', 0xFD => 'y', 0xFF => 'y',
        0xAB => '<', 0xBB => '>',
        // Ponctuation courbe : même largeur que sa contrepartie droite.
        0x92 => "'", 0x91 => "'", 0x93 => '"', 0x94 => '"',
    ];

    /** Largeurs Helvetica, en millièmes de corps. @var array<string,int> */
    private const WIDTHS = [
        ' ' => 278, '!' => 278, '"' => 355, '#' => 556, '$' => 556, '%' => 889, '&' => 667, "'" => 191,
        '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
        '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
        '8' => 556, '9' => 556, ':' => 278, ';' => 278, '<' => 584, '=' => 584, '>' => 584, '?' => 556,
        '@' => 1015, 'A' => 667, 'B' => 667, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778,
        'H' => 722, 'I' => 278, 'J' => 500, 'K' => 667, 'L' => 556, 'M' => 833, 'N' => 722, 'O' => 778,
        'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944,
        'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 278, '\\' => 278, ']' => 278, '^' => 469, '_' => 556,
        '`' => 333, 'a' => 556, 'b' => 556, 'c' => 500, 'd' => 556, 'e' => 556, 'f' => 278, 'g' => 556,
        'h' => 556, 'i' => 222, 'j' => 222, 'k' => 500, 'l' => 222, 'm' => 833, 'n' => 556, 'o' => 556,
        'p' => 556, 'q' => 556, 'r' => 333, 's' => 500, 't' => 278, 'u' => 556, 'v' => 500, 'w' => 722,
        'x' => 500, 'y' => 500, 'z' => 500, '{' => 334, '|' => 260, '}' => 334, '~' => 584,
        // Tiret cadratin, demi-cadratin, points de suspension.
        "\x97" => 1000, "\x96" => 556, "\x85" => 1000,
    ];

    /** Largeurs Helvetica-Bold. @var array<string,int> */
    private const WIDTHS_BOLD = [
        ' ' => 278, '!' => 333, '"' => 474, '#' => 556, '$' => 556, '%' => 889, '&' => 722, "'" => 238,
        '(' => 333, ')' => 333, '*' => 389, '+' => 584, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
        '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
        '8' => 556, '9' => 556, ':' => 333, ';' => 333, '<' => 584, '=' => 584, '>' => 584, '?' => 611,
        '@' => 975, 'A' => 722, 'B' => 722, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 611, 'G' => 778,
        'H' => 722, 'I' => 278, 'J' => 556, 'K' => 722, 'L' => 611, 'M' => 833, 'N' => 722, 'O' => 778,
        'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 611, 'U' => 722, 'V' => 667, 'W' => 944,
        'X' => 667, 'Y' => 667, 'Z' => 611, '[' => 333, '\\' => 278, ']' => 333, '^' => 584, '_' => 556,
        '`' => 333, 'a' => 556, 'b' => 611, 'c' => 556, 'd' => 611, 'e' => 556, 'f' => 333, 'g' => 611,
        'h' => 611, 'i' => 278, 'j' => 278, 'k' => 556, 'l' => 278, 'm' => 889, 'n' => 611, 'o' => 611,
        'p' => 611, 'q' => 611, 'r' => 389, 's' => 556, 't' => 333, 'u' => 611, 'v' => 556, 'w' => 778,
        'x' => 556, 'y' => 556, 'z' => 500, '{' => 389, '|' => 280, '}' => 389, '~' => 584,
        "\x97" => 1000, "\x96" => 556, "\x85" => 1000,
    ];
}
