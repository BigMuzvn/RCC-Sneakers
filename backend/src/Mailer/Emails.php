<?php

namespace Rcc\Mailer;

use Rcc\Config;

/**
 * Contenu des e-mails transactionnels.
 *
 * Mise en page par tableaux et styles en ligne : Outlook ignore les feuilles de
 * style externes et une bonne partie de la mise en page moderne. Bandeau sombre
 * repris du site, corps clair — un corps entièrement sombre s'affiche mal chez
 * plusieurs clients de messagerie et abîme la lisibilité.
 */
class Emails
{
    /** @return array{subject:string,html:string} */
    public static function verification(string $name, string $link): array
    {
        return [
            'subject' => 'Vérifiez votre adresse e-mail',
            'html' => self::layout(
                title: 'Bienvenue chez RCC',
                body: sprintf(
                    '<p style="margin:0 0 16px">Bonjour %s,</p>
                     <p style="margin:0 0 16px">Votre compte est créé. Il ne reste qu\'à confirmer
                     votre adresse e-mail pour recevoir le suivi de vos commandes.</p>',
                    self::escape($name)
                ),
                buttonLabel: 'Confirmer mon adresse',
                buttonLink: $link,
                footnote: 'Ce lien est valable 24 heures. Si vous n\'êtes pas à l\'origine
                           de cette inscription, ignorez ce message.'
            ),
        ];
    }

    /** @return array{subject:string,html:string} */
    public static function passwordReset(string $name, string $link): array
    {
        return [
            'subject' => 'Réinitialiser votre mot de passe',
            'html' => self::layout(
                title: 'Mot de passe oublié',
                body: sprintf(
                    '<p style="margin:0 0 16px">Bonjour %s,</p>
                     <p style="margin:0 0 16px">Vous avez demandé à choisir un nouveau mot de
                     passe. Ce lien vous y conduit.</p>',
                    self::escape($name)
                ),
                buttonLabel: 'Choisir un nouveau mot de passe',
                buttonLink: $link,
                footnote: 'Ce lien est valable 1 heure et ne fonctionne qu\'une fois. Si vous
                           n\'avez rien demandé, ignorez ce message : votre mot de passe
                           actuel reste valable.'
            ),
        ];
    }

    /**
     * Confirmation de commande.
     *
     * Les lignes viennent de la commande enregistrée, jamais du catalogue : ce
     * message doit refléter ce qui a été acheté, au prix payé ce jour-là.
     *
     * @param array<string,mixed> $order
     * @return array{subject:string,html:string}
     */
    public static function orderConfirmation(string $name, array $order): array
    {
        $lignes = '';

        foreach ($order['items'] as $item) {
            $lignes .= sprintf(
                '<tr>
                   <td style="padding:10px 0;border-bottom:1px solid #EDEFF2;font-size:13px;color:#17191C;">
                     <strong>%s</strong><br>
                     <span style="font-size:12px;color:#8A9099;">%s — taille %s × %d</span>
                   </td>
                   <td style="padding:10px 0;border-bottom:1px solid #EDEFF2;font-size:13px;color:#17191C;text-align:right;white-space:nowrap;">%s</td>
                 </tr>',
                self::escape($item['title']),
                self::escape($item['subtitle']),
                self::escape($item['size']),
                (int) $item['qty'],
                self::xof((int) $item['line_total_xof'])
            );
        }

        $recapitulatif = sprintf(
            '<table role="presentation" width="100%%" cellpadding="0" cellspacing="0" style="margin:8px 0 20px;font-family:Arial,sans-serif;">
               %s
               <tr><td style="padding:12px 0 2px;font-size:12px;color:#8A9099;">Sous-total</td>
                   <td style="padding:12px 0 2px;font-size:12px;color:#8A9099;text-align:right;">%s</td></tr>
               <tr><td style="padding:2px 0;font-size:12px;color:#8A9099;">Livraison — %s</td>
                   <td style="padding:2px 0;font-size:12px;color:#8A9099;text-align:right;">%s</td></tr>
               <tr><td style="padding:10px 0 0;font-size:14px;font-weight:bold;color:#17191C;border-top:2px solid #17191C;">Total</td>
                   <td style="padding:10px 0 0;font-size:14px;font-weight:bold;color:#17191C;text-align:right;border-top:2px solid #17191C;">%s</td></tr>
             </table>',
            $lignes,
            self::xof((int) $order['subtotal_xof']),
            self::escape($order['delivery_label']),
            self::xof((int) $order['delivery_fee_xof']),
            self::xof((int) $order['total_xof'])
        );

        return [
            'subject' => sprintf('Commande %s confirmée', $order['reference']),
            'html' => self::layout(
                title: 'Commande ' . self::escape($order['reference']),
                body: sprintf(
                    '<p style="margin:0 0 16px">Bonjour %s,</p>
                     <p style="margin:0 0 16px">Votre commande est enregistrée. Nous vous appelons au %s pour
                     confirmer la livraison et la disponibilité des tailles.</p>
                     %s
                     <p style="margin:0 0 6px;font-size:12px;color:#8A9099;">Livraison à cette adresse :</p>
                     <p style="margin:0 0 16px;font-size:13px;color:#3A3E45;">%s</p>
                     <p style="margin:0 0 16px;font-size:13px;color:#3A3E45;">Règlement <strong>en espèces à la
                     livraison</strong>. Préparez l\'appoint si possible.</p>',
                    self::escape($name),
                    self::escape($order['contact_phone']),
                    $recapitulatif,
                    self::escape($order['delivery_address'])
                ),
                buttonLabel: 'Suivre ma commande',
                buttonLink: rtrim((string) Config::get('app.url'), '/') . '/espace-client',
                footnote: 'Conservez cette référence : elle vous sera demandée pour toute question sur la commande.'
            ),
        ];
    }

    /** Formatage monétaire, aligné sur celui du site. */
    private static function xof(int $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' F CFA';
    }

    private static function layout(
        string $title,
        string $body,
        string $buttonLabel,
        string $buttonLink,
        string $footnote,
    ): string {
        $link = self::escape($buttonLink);

        return <<<HTML
        <!doctype html>
        <html lang="fr"><body style="margin:0;padding:0;background:#EDEFF2;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#EDEFF2;padding:24px 12px;">
        <tr><td align="center">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#FFFFFF;">

            <tr><td style="background:#0B0C0E;padding:22px 28px;">
              <span style="font-family:'Arial Black',Arial,sans-serif;font-size:20px;letter-spacing:.22em;color:#EDEFF2;">RCC</span>
              <span style="font-family:Arial,sans-serif;font-size:10px;letter-spacing:.24em;color:rgba(237,239,242,.55);text-transform:uppercase;">&nbsp;&nbsp;Sneakers</span>
            </td></tr>

            <tr><td style="padding:32px 28px 8px;font-family:Arial,sans-serif;">
              <h1 style="margin:0 0 20px;font-family:'Arial Black',Arial,sans-serif;font-size:19px;letter-spacing:.02em;color:#17191C;">{$title}</h1>
              <div style="font-size:14px;line-height:1.65;color:#3A3E45;">{$body}</div>
            </td></tr>

            <tr><td style="padding:12px 28px 28px;">
              <a href="{$link}" style="display:inline-block;background:#0B0C0E;color:#EDEFF2;font-family:Arial,sans-serif;font-size:12px;font-weight:bold;letter-spacing:.12em;text-transform:uppercase;text-decoration:none;padding:15px 26px;">{$buttonLabel}</a>
            </td></tr>

            <tr><td style="padding:0 28px 28px;font-family:Arial,sans-serif;font-size:12px;line-height:1.6;color:#8A9099;">
              <p style="margin:0 0 14px;">{$footnote}</p>
              <p style="margin:0;">Si le bouton ne fonctionne pas, copiez cette adresse dans votre navigateur :<br>
                <span style="color:#3A3E45;word-break:break-all;">{$link}</span></p>
            </td></tr>

            <tr><td style="background:#F4F5F7;padding:18px 28px;font-family:Arial,sans-serif;font-size:11px;color:#8A9099;">
              RCC Sneakers — Cotonou, Bénin
            </td></tr>

          </table>
        </td></tr></table>
        </body></html>
        HTML;
    }

    /**
     * Le nom vient du formulaire d'inscription : sans échappement, un client
     * nommé « <script> » ferait de chaque e-mail un vecteur d'injection.
     */
    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
