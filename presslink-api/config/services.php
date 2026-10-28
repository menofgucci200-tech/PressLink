<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Google Search Console : jeton de la balise <meta name="google-site-verification">
    // (le fichier public/googlee36bec6159a79626.html vérifie aussi le domaine).
    'google' => [
        'site_verification' => env('GOOGLE_SITE_VERIFICATION', '-p_mpG-fLnmC1h9wfvAwIEAOT3GEdXL7SoUghpxpJtk'),
    ],

    // Paiement en ligne des demandes d'abonnement (Wave, Orange Money, MTN,
    // carte). Sans clés, le paiement en ligne est masqué : l'équipe envoie
    // un lien Wave comme avant. Clés de test : pk_sandbox_ / sk_sandbox_.
    'geniuspay' => [
        'base_url' => env('GENIUSPAY_BASE_URL', 'https://geniuspay.ci/api/v1/merchant'),
        'sandbox' => (bool) env('GENIUSPAY_SANDBOX', true),
        'api_key' => env('GENIUSPAY_API_KEY'),
        'api_secret' => env('GENIUSPAY_API_SECRET'),
        'webhook_secret' => env('GENIUSPAY_WEBHOOK_SECRET'),
    ],

    'onesignal' => [
        // Vides en dev/pilote : les notifications sont loguées au lieu
        // d'être réellement envoyées (cf. App\Notifications\Channels\OneSignalChannel).
        'app_id' => env('ONESIGNAL_APP_ID'),
        'rest_api_key' => env('ONESIGNAL_REST_API_KEY'),
        // Canal Android "Commandes" (son personnalisé) créé dans le
        // dashboard OneSignal — null pour laisser Android/OneSignal
        // utiliser le canal générique par défaut.
        'android_channel_id' => env('ONESIGNAL_ANDROID_CHANNEL_ID'),
    ],

];
