<?php
// cors.php

// 1. Definir el origen permitido (tu frontend de Vue/Vite)
$origin = 'http://localhost:5173';

// 2. Cabeceras necesarias para el navegador
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
header("Access-Control-Allow-Credentials: true");

// 3. Manejar la petición "Preflight" (OPTIONS)
// El navegador envía un OPTIONS antes del POST para preguntar si tiene permiso.
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Si es OPTIONS, respondemos con un 200 OK y terminamos la ejecución aquí
    header("HTTP/1.1 200 OK");
    exit();
}