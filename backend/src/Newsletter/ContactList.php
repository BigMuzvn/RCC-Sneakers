<?php

namespace Rcc\Newsletter;

/**
 * Liste de contacts de la lettre d'information.
 *
 * Les campagnes se composent chez le prestataire, qui le fait mieux qu'un
 * éditeur maison ne le ferait — et le forfait plafonne de toute façon à 300
 * envois par jour. Le site se contente donc d'y déverser les inscrits.
 *
 * L'interface existe pour la même raison que celle du mailer : les tests ne
 * doivent joindre personne.
 */
interface ContactList
{
    /**
     * Rend false en cas d'échec, ne lève jamais d'exception : perdre un
     * inscrit parce qu'un service tiers est en panne serait absurde, la copie
     * locale suffit à rejouer la synchronisation plus tard.
     */
    public function add(string $email): bool;

    public function remove(string $email): bool;
}
