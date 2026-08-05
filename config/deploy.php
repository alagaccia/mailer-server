<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deploy degli asset compilati
    |--------------------------------------------------------------------------
    |
    | Parametri usati dal comando `php artisan deploy:assets` per sincronizzare
    | la cartella `public/build` (generata da `npm run build`) sul server di
    | produzione via rsync su SSH.
    |
    */

    'assets' => [

        'user' => env('DEPLOY_SSH_USER'),

        'host' => env('DEPLOY_SSH_HOST'),

        'port' => env('DEPLOY_SSH_PORT', 22),

        // Cartella locale da inviare (relativa alla radice del progetto o assoluta).
        'local_path' => env('DEPLOY_LOCAL_PATH', 'public/build'),

        // Cartella remota di destinazione (percorso assoluto sul server).
        'remote_path' => env('DEPLOY_REMOTE_PATH'),

        // Opzioni passate a rsync.
        'rsync_options' => env('DEPLOY_RSYNC_OPTIONS', '-avz --delete'),

    ],

];
