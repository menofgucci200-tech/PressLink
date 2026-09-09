<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            // Écrit directement dans le dossier public (pas de lien
            // symbolique `public/storage`) pour deux raisons cumulées,
            // découvertes en production sur l'hébergement LWS :
            // 1. Apache y renvoie un 403 sur TOUT chemin contenant
            //    `/storage/` — lien symbolique ou non, permissions
            //    correctes ou non — un filtrage de sécurité générique par
            //    nom de dossier propre à leur configuration par défaut.
            // 2. Le docroot Apache réel (`/htdocs`, un `index.php`
            //    autonome qui démarre Laravel depuis cette app) est un
            //    dossier séparé de `public/` de ce dépôt — `public_path()`
            //    n'y correspond donc pas du tout en prod. `PUBLIC_DISK_ROOT`
            //    permet de le pointer explicitement sans coder ce chemin
            //    serveur en dur dans un fichier versionné ; par défaut
            //    (dev local, où public/ EST le docroot), il vaut public_path('uploads').
            'root' => env('PUBLIC_DISK_ROOT', public_path('uploads')),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/uploads',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
