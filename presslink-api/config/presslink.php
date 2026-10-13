<?php

return [

    /*
    | Support PressLink — le même numéro sert à l'appel et à WhatsApp.
    | Utilisé par la page d'accueil publique et la page Support du dashboard.
    */
    'support' => [
        'phone_display' => '07 18 00 22 93',
        'phone_e164' => '+2250718002293',
        // Adresse unique pour les messages (formulaire de contact, support, juridique).
        'email' => env('SUPPORT_EMAIL', 'contact@presslink.org'),
    ],

    /*
    | Identité légale de l'éditeur, affichée dans les mentions légales, les CGU
    | et la politique de confidentialité. Les champs vides (null) ne sont pas
    | affichés : à compléter dans le .env dès que les informations sont connues.
    */
    'legal' => [
        'company' => env('LEGAL_COMPANY', 'PressLink'),
        'legal_form' => env('LEGAL_FORM'),
        'rccm' => env('LEGAL_RCCM'),
        'tax_id' => env('LEGAL_TAX_ID'),
        'address' => env('LEGAL_ADDRESS', 'Abidjan, Côte d\'Ivoire'),
        'publisher' => env('LEGAL_PUBLISHER'),
        'email' => env('LEGAL_EMAIL', env('SUPPORT_EMAIL', 'contact@presslink.org')),
        'updated_at' => '13 octobre 2026',
    ],

];
