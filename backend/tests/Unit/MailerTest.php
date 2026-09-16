<?php

namespace Rcc\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Rcc\Config;
use Rcc\Mailer\BrevoMailer;
use Rcc\Mailer\Emails;

class MailerTest extends TestCase
{
    protected function setUp(): void
    {
        Config::load([
            'app' => ['env' => 'local', 'url' => 'http://localhost:5173'],
            'mail' => [
                'brevo_key' => 'xkeysib-factice',
                'from_email' => 'contact@rccsneakers.test',
                'from_name' => 'RCC Sneakers',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Config::load(require dirname(__DIR__, 2) . '/config.php');
    }

    public function test_la_charge_utile_suit_le_format_attendu_par_brevo(): void
    {
        $payload = (new BrevoMailer())->payload(
            'client@exemple.com',
            'Lemaye',
            'Vérifiez votre adresse',
            '<p>Bonjour</p>'
        );

        $this->assertSame('contact@rccsneakers.test', $payload['sender']['email']);
        $this->assertSame('RCC Sneakers', $payload['sender']['name']);
        $this->assertSame('client@exemple.com', $payload['to'][0]['email']);
        $this->assertSame('Vérifiez votre adresse', $payload['subject']);
        $this->assertSame('<p>Bonjour</p>', $payload['htmlContent']);
    }

    /**
     * Une version texte accompagne toujours l'HTML : sans elle, les filtres
     * anti-spam pénalisent le message, et l'expéditeur est déjà une adresse
     * Gmail qui échoue l'alignement SPF.
     */
    public function test_une_version_texte_accompagne_lhtml(): void
    {
        $payload = (new BrevoMailer())->payload(
            'client@exemple.com',
            'Lemaye',
            'Sujet',
            '<p>Bonjour <strong>Lemaye</strong></p>'
        );

        $this->assertArrayHasKey('textContent', $payload);
        $this->assertStringContainsString('Bonjour Lemaye', $payload['textContent']);
        $this->assertStringNotContainsString('<strong>', $payload['textContent']);
    }

    public function test_le_lien_de_verification_figure_dans_le_message(): void
    {
        $email = Emails::verification('Lemaye', 'http://localhost:5173/compte/verifier?token=abc123');

        $this->assertStringContainsString('http://localhost:5173/compte/verifier?token=abc123', $email['html']);
        $this->assertNotSame('', $email['subject']);
    }

    public function test_le_lien_de_reinitialisation_figure_dans_le_message(): void
    {
        $email = Emails::passwordReset('Lemaye', 'http://localhost:5173/compte/reinitialiser?token=xyz');

        $this->assertStringContainsString('http://localhost:5173/compte/reinitialiser?token=xyz', $email['html']);
    }

    /**
     * Le nom vient du formulaire d'inscription. Sans échappement, un client
     * nommé « <script> » ferait de chaque e-mail un vecteur d'injection.
     */
    public function test_le_nom_du_client_est_echappe(): void
    {
        $email = Emails::verification('<script>alert(1)</script>', 'http://exemple.test/x');

        $this->assertStringNotContainsString('<script>', $email['html']);
        $this->assertStringContainsString('&lt;script&gt;', $email['html']);
    }

    public function test_les_messages_sont_en_francais(): void
    {
        $verification = Emails::verification('Lemaye', 'http://exemple.test/x');
        $reset = Emails::passwordReset('Lemaye', 'http://exemple.test/x');

        $this->assertMatchesRegularExpression('/vérifi|confirm/i', $verification['subject']);
        $this->assertMatchesRegularExpression('/mot de passe/i', $reset['subject']);
    }

    /**
     * Le point décisif. Si Brevo est injoignable, l'envoi doit rendre false et
     * non lever une exception : une panne chez un tiers ne doit pas empêcher
     * un client de créer son compte, sinon la boutique ferme avec Brevo.
     */
    public function test_un_envoi_impossible_rend_false_sans_lever_dexception(): void
    {
        Config::load([
            'app' => ['env' => 'local'],
            'mail' => [
                'brevo_key' => 'xkeysib-factice',
                'from_email' => 'contact@rccsneakers.test',
                'from_name' => 'RCC Sneakers',
                // Hôte volontairement inexistant : la résolution DNS échoue.
                'endpoint' => 'https://brevo.invalide.test/v3/smtp/email',
                'timeout' => 2,
            ],
        ]);

        $result = (new BrevoMailer())->send('client@exemple.com', 'Lemaye', 'Sujet', '<p>x</p>');

        $this->assertFalse($result);
    }
}
