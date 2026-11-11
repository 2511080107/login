<?php
// ============================================================
// CONFIGURACIÓN DE LA BASE DE DATOS
// ============================================================

// --- Datos LOCAL (Laragon) ---
$hostLocal = 'localhost';
$dbLocal   = 'login_demo';
$userLocal = 'root';
$passLocal = '';

// --- Datos INFINITYFREE (producción) ---
// Copia EXACTAMENTE desde Panel -> MySQL Databases -> Current Databases
$hostProd = 'sql310.infinityfree.com';
$dbProd   = 'if0_43405109_login_demo';
$userProd = 'if0_43405109';
$passProd = 'lk291006';

// Detección automática: Laragon (local) vs InfinityFree (servidor)
$servidor = $_SERVER['SERVER_NAME'] ?? '';
$ip       = $_SERVER['REMOTE_ADDR'] ?? '';
$esLocal  = in_array($servidor, ['localhost', '127.0.0.1', '::1'], true)
         || in_array($ip, ['127.0.0.1', '::1'], true);

if ($esLocal) {
    $host = $hostLocal;
    $db   = $dbLocal;
    $user = $userLocal;
    $pass = $passLocal;
} else {
    $host = $hostProd;
    $db   = $dbProd;
    $user = $userProd;
    $pass = $passProd;
}

// Limpia espacios accidentales al copiar/pegar
$host = trim($host);
$db   = trim($db);
$user = trim($user);

$debug = true; // pon false cuando ya funcione

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    error_log('DB connection error: ' . $e->getMessage());

    if ($debug) {
        die('Error de conexión a la base de datos: ' . htmlspecialchars($e->getMessage()));
    }
    die('Error de conexión a la base de datos.');
}
