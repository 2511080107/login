<?php
session_start();
require 'db.php';
require 'google_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido.']);
    exit;
}

$credential = $_POST['credential'] ?? '';

if ($credential === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No se recibió el token de Google.']);
    exit;
}

// ------------------------------------------------------------
// Verificar el ID token contra los servidores de Google
// ------------------------------------------------------------
$url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
} else {
    $response = @file_get_contents($url);
}

$data = $response ? json_decode($response, true) : null;

if (!is_array($data) || !empty($data['error_description']) || empty($data['email'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No se pudo verificar la cuenta de Google.']);
    exit;
}

// El token debe haber sido emitido para ESTA aplicación
if (($data['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Token de Google no válido para esta aplicación.']);
    exit;
}

// El correo debe estar verificado por Google
if (($data['email_verified'] ?? 'false') !== 'true') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'El correo de Google no está verificado.']);
    exit;
}

// El token no debe estar expirado
if (isset($data['exp']) && (int) $data['exp'] < time()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'La sesión de Google expiró, intenta de nuevo.']);
    exit;
}

// ------------------------------------------------------------
// Buscar o crear el usuario en la base de datos
// ------------------------------------------------------------
$email  = $data['email'];
$nombre = $data['name'] ?? $data['given_name'] ?? explode('@', $email)[0];

$stmt = $pdo->prepare('SELECT id, nombre FROM usuarios WHERE email = ?');
$stmt->execute([$email]);
$usuario = $stmt->fetch();

if ($usuario) {
    $id     = (int) $usuario['id'];
    $nombre = $usuario['nombre'];
} else {
    // Las cuentas de Google no usan contraseña: se guarda una aleatoria.
    $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, email, password) VALUES (?, ?, ?)');
    $stmt->execute([$nombre, $email, $password]);
    $id = (int) $pdo->lastInsertId();
}

session_regenerate_id(true);
$_SESSION['usuario_id'] = $id;
$_SESSION['nombre']     = $nombre;
$_SESSION['email']      = $email;
$_SESSION['foto']       = $data['picture'] ?? ('https://www.gravatar.com/avatar/' . md5(strtolower(trim($email))) . '?d=identicon&s=64');

echo json_encode(['ok' => true, 'redirect' => 'panel.php']);
