<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dossier exports
    |--------------------------------------------------------------------------
    | Exported dossiers (PDF + HTML, English and French) land in
    | <dir>/<target-slug>/. The project folder is bind-mounted into the
    | containers, so the same files appear on the host under `dossier-exports/`.
    | `host_dir` is the project path as the user sees it on their own machine
    | (set by scripts/bootstrap.sh) so the app can say where the files are.
    */

    'dossier_dir' => env('SECTF_DOSSIER_DIR', base_path('dossier-exports')),

    'host_dir' => env('HOST_PROJECT_DIR'),

    'chromium' => env('SECTF_CHROMIUM', '/usr/bin/chromium'),

];
