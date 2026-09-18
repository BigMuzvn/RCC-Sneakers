<?php

namespace Rcc\Tests\Feature;

use Rcc\Database;
use Rcc\Settings;
use Rcc\Tests\ApiTestCase;

/**
 * La facture PDF.
 *
 * Deux natures de contrôles, et il faut les deux : que le document soit un PDF
 * valide et lisible, et qu'il ne parte qu'à qui y a droit. Une facture porte le
 * nom, le téléphone et l'adresse du client — c'est la pièce la plus indiscrète
 * que la boutique produise.
 */
class InvoiceTest extends ApiTestCase
{
    private const PRODUIT = 1;
    private const TAILLE = '42';

    private function commander(): string
    {
        $this->setStock('sneaker', self::PRODUIT, self::TAILLE, 5);

        $zone = Database::first('SELECT id FROM delivery_zones WHERE is_active = 1 ORDER BY position, id');

        $response = $this->post('/orders', [
            'name' => 'Lemaye Godson',
            'email' => 'client@exemple.bj',
            'phone' => '0197000011',
            'address' => 'Carré 1234, Gbédjromèdé, Cotonou',
            'payment_method' => 'cash',
            'zone' => $zone['id'],
            'items' => [
                ['item_type' => 'sneaker', 'item_id' => self::PRODUIT, 'size' => self::TAILLE, 'qty' => 1],
            ],
        ]);

        $this->assertSame(
            201,
            $response->status,
            'la commande de départ doit passer : ' . json_encode($response->payload)
        );

        return $response->payload['data']['order']['reference'];
    }

    public function test_le_client_telecharge_sa_facture(): void
    {
        $this->loginAsCustomer();
        $reference = $this->commander();

        $response = $this->get("/orders/{$reference}/facture");

        $this->assertSame(200, $response->status);
        $this->assertNotNull($response->binary);
        $this->assertStringStartsWith('%PDF-1.4', $response->binary);
        $this->assertStringEndsWith('%%EOF', $response->binary);
        $this->assertSame('application/pdf', $response->headers['Content-Type']);
        $this->assertStringContainsString(
            'filename="facture-' . strtolower($reference) . '.pdf"',
            $response->headers['Content-Disposition']
        );
    }

    /**
     * Le document porte bien la commande demandée, et non un gabarit vide. On
     * cherche la référence dans les octets : le texte d'un PDF non compressé
     * s'y lit tel quel.
     */
    public function test_la_facture_porte_la_commande(): void
    {
        $this->loginAsCustomer();
        Settings::set('shop_city', 'Cotonou, Bénin');

        $reference = $this->commander();
        $response = $this->get("/orders/{$reference}/facture");

        $this->assertStringContainsString($reference, $response->binary);
        $this->assertStringContainsString('FACTURE', $response->binary);
        $this->assertStringContainsString('Lemaye Godson', $response->binary);
    }

    /**
     * Le cœur du sujet. Deux clients, deux commandes : la référence de l'un ne
     * doit rien donner à l'autre, et surtout pas un 403 qui confirmerait son
     * existence.
     */
    public function test_la_facture_d_un_autre_client_reste_hors_de_portee(): void
    {
        $this->loginAsCustomer('premier@exemple.bj', '0197000021');
        $reference = $this->commander();

        $this->forgetCookies();
        $this->loginAsCustomer('second@exemple.bj', '0197000022');

        $response = $this->get("/orders/{$reference}/facture");

        $this->assertSame(404, $response->status);
        $this->assertNull($response->binary);
    }

    public function test_un_visiteur_n_obtient_rien(): void
    {
        $this->loginAsCustomer();
        $reference = $this->commander();

        $this->forgetCookies();

        $response = $this->get("/orders/{$reference}/facture");

        $this->assertSame(401, $response->status);
        $this->assertNull($response->binary);
    }

    /**
     * L'administrateur, lui, atteint toutes les factures : il répond au
     * téléphone à des gens qui n'ont pas retrouvé la leur.
     */
    public function test_l_administrateur_atteint_n_importe_quelle_facture(): void
    {
        $this->loginAsCustomer('client.facture@exemple.bj', '0197000031');
        $reference = $this->commander();

        $this->forgetCookies();
        $this->loginAsAdmin();

        $response = $this->get("/orders/{$reference}/facture");

        $this->assertSame(200, $response->status);
        $this->assertStringStartsWith('%PDF', $response->binary);
    }

    /**
     * Les mentions légales n'apparaissent que renseignées. Un RCCM inventé sur
     * un document comptable est pire que pas de RCCM du tout.
     */
    public function test_les_mentions_legales_ne_s_impriment_que_remplies(): void
    {
        $this->loginAsCustomer();
        $reference = $this->commander();

        $sans = $this->get("/orders/{$reference}/facture");
        $this->assertStringNotContainsString('RCCM', $sans->binary);

        Settings::set('shop_rccm', 'RB/COT/26 B 12345');
        $avec = $this->get("/orders/{$reference}/facture");

        $this->assertStringContainsString('RCCM', $avec->binary);
        $this->assertStringContainsString('RB/COT/26 B 12345', $avec->binary);
    }

    public function test_une_reference_inconnue_ne_produit_pas_de_document(): void
    {
        $this->loginAsCustomer();

        $response = $this->get('/orders/RCC-000000-ZZZZ/facture');

        $this->assertSame(404, $response->status);
        $this->assertNull($response->binary);
    }
}
