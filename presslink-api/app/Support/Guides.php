<?php

namespace App\Support;

/**
 * Guides pratiques du site (référencement local) : conseils utiles aux
 * gérants de pressing en Côte d'Ivoire, chacun renvoyant vers le logiciel.
 */
class Guides
{
    /** @var array<string, array{title: string, description: string, published: string}> */
    public const ALL = [
        'logiciel-gestion-pressing-abidjan' => [
            'title' => 'Logiciel de pressing à Abidjan : ce qu\'il doit faire',
            'description' => 'Commandes, reçus, paiements mobiles, caisse, coupures d\'Internet : les 7 points à vérifier avant de choisir le logiciel de votre pressing à Abidjan.',
            'published' => '2026-10-23',
        ],
        'prevenir-clients-vetements-prets' => [
            'title' => 'Prévenir ses clients quand leurs vêtements sont prêts',
            'description' => 'Appels, SMS, WhatsApp ou notification automatique : prévenir les clients de votre pressing sans y passer la journée, et vider les étagères.',
            'published' => '2026-10-23',
        ],
        'encaisser-wave-orange-money-pressing' => [
            'title' => 'Encaisser par Wave et Orange Money au pressing',
            'description' => 'Espèces, Wave, Orange Money, MTN, Moov : suivre chaque paiement, les acomptes et le reste à payer, et tomber juste à la clôture de caisse.',
            'published' => '2026-10-23',
        ],
        'pressing-coupure-internet' => [
            'title' => 'Gérer son pressing quand Internet coupe',
            'description' => 'Coupure de réseau ou de courant : continuer à enregistrer les dépôts, encaisser et remettre les vêtements, puis tout retrouver au retour du réseau.',
            'published' => '2026-10-23',
        ],
    ];
}
