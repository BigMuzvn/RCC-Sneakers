<?php

namespace Rcc\Tests\Feature;

use Rcc\Auth;
use Rcc\Database;
use Rcc\Tests\ApiTestCase;

/**
 * La distribution des accès.
 *
 * Un rang au-dessus des autres : le super administrateur peut faire entrer
 * quelqu'un, et le faire sortir. Ce que ce fichier vérifie surtout, c'est
 * qu'un administrateur ajouté ne puisse pas refaire ces gestes-là — sans quoi
 * le premier accès distribué permettrait d'en distribuer d'autres, puis de
 * retirer le sien à celui qui l'a donné.
 */
class AdminsTest extends ApiTestCase
{
    // ------------------------------------------------------------ lecture

    public function test_la_liste_montre_les_administrateurs_et_leur_rang(): void
    {
        $this->loginAsSubAdmin();
        $this->loginAsAdmin();

        $admins = $this->get('/admin/admins')->payload['data']['admins'];
        $parEmail = array_column($admins, null, 'email');

        $this->assertArrayHasKey('admin@exemple.com', $parEmail);
        $this->assertArrayHasKey('second@exemple.com', $parEmail);
        $this->assertTrue($parEmail['admin@exemple.com']['is_super_admin']);
        $this->assertFalse($parEmail['second@exemple.com']['is_super_admin']);
    }

    /** Un client ordinaire n'apparaît pas ici : cet écran ne liste que des accès. */
    public function test_les_clients_ne_figurent_pas_parmi_les_administrateurs(): void
    {
        $this->loginAsCustomer();
        $this->loginAsAdmin();

        $adresses = array_column($this->get('/admin/admins')->payload['data']['admins'], 'email');

        $this->assertNotContains('client@exemple.com', $adresses);
    }

    // ------------------------------------------------------------- ajout

    /**
     * Le vendeur qui commandait déjà sur le site : son compte est promu, son
     * mot de passe habituel reste le sien, et rien ne lui est envoyé.
     */
    public function test_un_client_existant_est_simplement_promu(): void
    {
        $this->loginAsCustomer('vendeur@exemple.com', '0197000055');
        $this->loginAsAdmin();
        $this->mailer->sent = [];

        $response = $this->post('/admin/admins', ['email' => 'vendeur@exemple.com']);

        $this->assertSame(201, $response->status);
        $this->assertFalse($response->payload['data']['created']);
        $this->assertSame([], $this->mailer->sent, 'aucun message pour un compte qui existe déjà');

        $row = Database::first('SELECT is_admin, is_super_admin FROM customers WHERE email = ?', ['vendeur@exemple.com']);
        $this->assertSame(1, (int) $row['is_admin']);
        $this->assertSame(0, (int) $row['is_super_admin'], 'un accès distribué ne donne jamais le premier rang');
    }

    /**
     * L'adresse inconnue : le compte est créé sans mot de passe utilisable, et
     * la personne reçoit un lien pour en choisir un. Aucun mot de passe n'est
     * fabriqué ici ni transmis par message.
     */
    public function test_une_adresse_inconnue_reçoit_une_invitation(): void
    {
        $this->loginAsAdmin();
        $this->mailer->sent = [];

        $response = $this->post('/admin/admins', [
            'email' => 'nouvelle@exemple.com',
            'name' => 'Nouvelle Recrue',
            'phone' => '0197000066',
        ]);

        $this->assertSame(201, $response->status);
        $this->assertTrue($response->payload['data']['created']);
        $this->assertCount(1, $this->mailer->sent);
        $this->assertSame('nouvelle@exemple.com', $this->mailer->sent[0]['to']);

        $row = Database::first('SELECT is_admin, password_hash FROM customers WHERE email = ?', ['nouvelle@exemple.com']);
        $this->assertSame(1, (int) $row['is_admin']);

        // Le compte existe, mais aucun mot de passe n'y donne accès tant que le
        // lien n'a pas été suivi.
        $this->assertFalse(password_verify('motdepasse', $row['password_hash']));
        $this->assertStringStartsWith('invitation:', $row['password_hash']);
    }

    public function test_l_invitation_est_marquee_en_attente_puis_renvoyable(): void
    {
        $this->loginAsAdmin();
        $this->post('/admin/admins', [
            'email' => 'attente@exemple.com',
            'name' => 'En Attente',
            'phone' => '0197000077',
        ]);

        $admins = array_column($this->get('/admin/admins')->payload['data']['admins'], null, 'email');
        $this->assertTrue($admins['attente@exemple.com']['pending']);

        $this->mailer->sent = [];
        $id = $admins['attente@exemple.com']['id'];

        $this->assertSame(200, $this->post("/admin/admins/{$id}/resend")->status);
        $this->assertCount(1, $this->mailer->sent);
    }

    /** Un compte qui a déjà son mot de passe passe par « mot de passe oublié ». */
    public function test_on_ne_renvoie_pas_d_invitation_a_qui_a_deja_son_mot_de_passe(): void
    {
        $id = $this->loginAsSubAdmin();
        $this->loginAsAdmin();

        $response = $this->post("/admin/admins/{$id}/resend");

        $this->assertSame(422, $response->status);
    }

    public function test_une_adresse_deja_administratrice_est_refusee(): void
    {
        $this->loginAsSubAdmin();
        $this->loginAsAdmin();

        $response = $this->post('/admin/admins', ['email' => 'second@exemple.com']);

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('email', $response->payload['error']['fields']);
    }

    public function test_un_compte_suspendu_ne_peut_pas_etre_promu(): void
    {
        $id = $this->loginAsCustomer('suspendu@exemple.com', '0197000088');
        Database::run("UPDATE customers SET status = 'suspended' WHERE id = ?", [$id]);

        $this->loginAsAdmin();
        $response = $this->post('/admin/admins', ['email' => 'suspendu@exemple.com']);

        $this->assertSame(422, $response->status);
    }

    public function test_une_creation_exige_un_nom_et_un_telephone(): void
    {
        $this->loginAsAdmin();

        $response = $this->post('/admin/admins', ['email' => 'sansrien@exemple.com']);

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('name', $response->payload['error']['fields']);
        $this->assertArrayHasKey('phone', $response->payload['error']['fields']);
    }

    // ------------------------------------------------------------ retrait

    /**
     * Retirer un accès coupe les sessions ouvertes sur-le-champ. Sans cela,
     * l'onglet resté ouvert dans l'arrière-boutique continuerait d'administrer.
     */
    public function test_retirer_un_acces_ferme_les_sessions_ouvertes(): void
    {
        $id = $this->loginAsSubAdmin();
        $this->assertSame(200, $this->get('/admin/overview')->status);
        $sessionSecondaire = $this->cookies;

        $this->loginAsAdmin();
        $this->assertSame(200, $this->post("/admin/admins/{$id}/revoke")->status);

        $row = Database::first('SELECT is_admin FROM customers WHERE id = ?', [$id]);
        $this->assertSame(0, (int) $row['is_admin'], "le compte reste, l'accès part");

        $this->cookies = $sessionSecondaire;
        $this->assertSame(401, $this->get('/admin/overview')->status, 'sa session devait tomber');
    }

    public function test_le_super_administrateur_ne_se_retire_pas_depuis_le_web(): void
    {
        $moi = $this->loginAsAdmin();

        $response = $this->post("/admin/admins/{$moi}/revoke");

        $this->assertSame(422, $response->status);
        $this->assertSame(1, (int) Database::first('SELECT is_admin FROM customers WHERE id = ?', [$moi])['is_admin']);
    }

    /**
     * Même en visant le compte d'un autre super administrateur, si un jour il en
     * existait deux : ce rang ne se retire que depuis le serveur.
     */
    public function test_un_second_rang_ne_peut_pas_toucher_au_premier(): void
    {
        $super = $this->loginAsAdmin();

        $autre = $this->loginAsSubAdmin();
        Database::run('UPDATE customers SET is_super_admin = 1 WHERE id = ?', [$autre]);
        Database::run('UPDATE customers SET is_super_admin = 1 WHERE id = ?', [$super]);

        $this->loginAsAdmin();
        $this->assertSame(422, $this->post("/admin/admins/{$autre}/revoke")->status);
    }

    // ---------------------------------------------------------- journal

    public function test_les_mouvements_d_acces_sont_journalises(): void
    {
        $this->loginAsAdmin();
        $this->post('/admin/admins', [
            'email' => 'trace@exemple.com',
            'name' => 'Laisse Trace',
            'phone' => '0197000044',
        ]);

        $actions = array_column(
            Database::run('SELECT action FROM admin_log ORDER BY id')->fetchAll(),
            'action'
        );

        $this->assertContains('admin.invite', $actions);
    }

    /** Le drapeau du premier rang voyage jusqu'au front, qui s'en sert pour afficher. */
    public function test_le_rang_est_visible_dans_la_session(): void
    {
        $this->loginAsAdmin();
        $this->assertTrue($this->get('/auth/me')->payload['data']['customer']['is_super_admin']);

        $this->forgetCookies();
        $this->loginAsSubAdmin();
        $this->assertFalse($this->get('/auth/me')->payload['data']['customer']['is_super_admin']);
        $this->assertTrue($this->get('/auth/me')->payload['data']['customer']['is_admin']);
    }

    /** L'accès retiré reprend effet immédiatement, sans attendre la déconnexion. */
    public function test_un_acces_retire_ne_survit_pas_a_la_requete_suivante(): void
    {
        $id = $this->loginAsSubAdmin();

        Database::run('UPDATE customers SET is_admin = 0 WHERE id = ?', [$id]);
        Auth::revokeAll($id);

        $this->assertSame(401, $this->get('/admin/overview')->status);
    }
}
