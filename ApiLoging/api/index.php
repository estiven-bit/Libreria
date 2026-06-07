<?php
// Puente para Vercel: redirige todas las peticiones al entry point principal.
// Vercel requiere que los archivos PHP estén dentro de la carpeta /api.
require __DIR__ . '/../index.php';
